<?php
require_once '../config/database.php';

class SAWCalculator {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getEligibilityThreshold($jenis_kredit) {
        return $jenis_kredit === 'KUR' ? 75.0 : 70.0;
    }

    private function normalizeText($value)
    {
        return strtolower(trim((string) $value));
    }

    private function getApplicationContext($pengajuan_id)
    {
        $sql = "SELECT p.id, p.anggota_id, p.jenis_kredit, p.jumlah_pinjaman, p.detail_pinjaman, p.status,
                       a.nama, a.tanggal_lahir
                FROM pengajuan p
                JOIN anggota a ON p.anggota_id = a.id
                WHERE p.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    private function scoreKtaPendapatan($detail)
    {
        $penghasilan = (float) ($detail['penghasilan_bulanan'] ?? 0);
        $pinjaman = (float) ($detail['jumlah_pinjaman'] ?? 0);

        if ($penghasilan <= 0 || $pinjaman <= 0) {
            return 1.0;
        }

        $ratio = $penghasilan / $pinjaman;
        if ($ratio >= 4) return 5.0;
        if ($ratio >= 3) return 4.0;
        if ($ratio >= 2) return 3.0;
        if ($ratio >= 1.5) return 2.0;
        return 1.0;
    }

    private function scoreKtaRiwayatPinjaman($context)
    {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*) AS total_prev,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified_count
             FROM pengajuan
             WHERE anggota_id = ? AND id < ?"
        );
        $stmt->bind_param('ii', $context['anggota_id'], $context['id']);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_assoc();

        $totalPrev = (int) ($history['total_prev'] ?? 0);
        $acceptedCount = (int) ($history['accepted_count'] ?? 0);
        $rejectedCount = (int) ($history['rejected_count'] ?? 0);
        $verifiedCount = (int) ($history['verified_count'] ?? 0);

        if ($totalPrev === 0) {
            return 4.0;
        }
        if ($rejectedCount > 0) {
            return 1.0;
        }
        if ($acceptedCount > 0) {
            return 5.0;
        }
        if ($verifiedCount > 0) {
            return 4.0;
        }

        return 3.0;
    }

    private function scoreKtaUsia($context)
    {
        if (empty($context['tanggal_lahir'])) {
            return 3.0;
        }

        $birthDate = new DateTime($context['tanggal_lahir']);
        $age = (int) $birthDate->diff(new DateTime())->y;

        if ($age >= 25 && $age <= 45) return 5.0;
        if (($age >= 21 && $age < 25) || ($age > 45 && $age <= 55)) return 4.0;
        if (($age >= 18 && $age < 21) || ($age > 55 && $age <= 60)) return 2.0;
        return 1.0;
    }

    private function scoreKtaStabilitasPekerjaan($detail)
    {
        $status = $this->normalizeText($detail['status_pekerjaan_asli'] ?? ($detail['status_pekerjaan'] ?? ''));
        $jumlahTanggungan = (int) ($detail['jumlah_tanggungan'] ?? 0);

        if ($status === 'pelajar/mahasiswa') {
            if ($jumlahTanggungan <= 1) return 5.0;
            if ($jumlahTanggungan === 2) return 4.0;
            if ($jumlahTanggungan === 3) return 3.0;
            if ($jumlahTanggungan === 4) return 2.0;
            return 1.0;
        }

        $lamaBekerja = (int) ($detail['lama_bekerja_bulan'] ?? 0);

        if ($status === 'tidak bekerja') {
            return 1.0;
        }

        $score = 1.0;
        if ($lamaBekerja >= 36) {
            $score = 5.0;
        } elseif ($lamaBekerja >= 24) {
            $score = 4.0;
        } elseif ($lamaBekerja >= 12) {
            $score = 3.0;
        } elseif ($lamaBekerja >= 6) {
            $score = 2.0;
        }

        if (in_array($status, ['karyawan', 'pns', 'pegawai tetap', 'bumn', 'pensiunan'], true)) {
            $score = min(5.0, $score + 1.0);
        } elseif (in_array($status, ['honorer', 'buruh', 'wiraswasta'], true)) {
            $score = max(2.0, $score);
        }

        return $score;
    }

    private function scoreKtaBebanCicilan($detail)
    {
        $penghasilan = (float) ($detail['penghasilan_bulanan'] ?? 0);
        $cicilan = (float) ($detail['beban_cicilan_bulanan'] ?? 0);

        if ($penghasilan <= 0) {
            return 1.0;
        }

        $ratio = $cicilan / $penghasilan;
        if ($ratio <= 0.10) return 5.0;
        if ($ratio <= 0.20) return 4.0;
        if ($ratio <= 0.30) return 3.0;
        if ($ratio <= 0.40) return 2.0;
        return 1.0;
    }

    private function scoreKurLamaUsaha($detail)
    {
        $lama = (int) ($detail['lama_usaha_bulan'] ?? 0);
        if ($lama >= 60) return 5.0;
        if ($lama >= 36) return 4.0;
        if ($lama >= 24) return 3.0;
        if ($lama >= 12) return 2.0;
        return 1.0;
    }

    private function scoreKurOmzet($detail)
    {
        $omzet = (float) ($detail['omzet_bulanan'] ?? 0);
        $pinjaman = (float) ($detail['jumlah_pinjaman'] ?? 0);

        if ($omzet <= 0 || $pinjaman <= 0) {
            return 1.0;
        }

        $ratio = $omzet / $pinjaman;
        if ($ratio >= 5) return 5.0;
        if ($ratio >= 4) return 4.0;
        if ($ratio >= 3) return 3.0;
        if ($ratio >= 2) return 2.0;
        return 1.0;
    }

    private function scoreKurLabaBersih($detail)
    {
        $omzet = (float) ($detail['omzet_bulanan'] ?? 0);
        $laba = (float) ($detail['laba_bersih_bulanan'] ?? 0);

        if ($omzet <= 0) {
            return 1.0;
        }

        $margin = $laba / $omzet;
        if ($margin >= 0.30) return 5.0;
        if ($margin >= 0.20) return 4.0;
        if ($margin >= 0.15) return 3.0;
        if ($margin >= 0.10) return 2.0;
        return 1.0;
    }

    private function scoreKurLegalitas($detail)
    {
        $legalitas = $this->normalizeText($detail['legalitas_usaha'] ?? '');

        if ($legalitas === 'nib') return 5.0;
        if ($legalitas === 'siup') return 4.0;
        if ($legalitas === 'sku') return 3.0;
        if ($legalitas === 'belum ada') return 1.0;
        return 2.0;
    }

    private function scoreKurJumlahKaryawan($detail)
    {
        $jumlah = (int) ($detail['jumlah_karyawan'] ?? 0);

        if ($jumlah >= 10) return 5.0;
        if ($jumlah >= 5) return 4.0;
        if ($jumlah >= 3) return 3.0;
        if ($jumlah >= 1) return 2.0;
        return 1.0;
    }

    public function buildAutomaticAssessments($pengajuan_id)
    {
        $context = $this->getApplicationContext($pengajuan_id);
        if (!$context) {
            return false;
        }

        $detail = json_decode($context['detail_pinjaman'] ?? '', true);
        if (!is_array($detail)) {
            $detail = [];
        }
        $detail['jumlah_pinjaman'] = (float) $context['jumlah_pinjaman'];

        $criteria = $this->getCriteria($context['jenis_kredit']);
        if (empty($criteria)) {
            return false;
        }

        $scoreMap = [];
        foreach ($criteria as $row) {
            $name = $this->normalizeText($row['nama']);
            if ($context['jenis_kredit'] === 'KTA') {
                if (str_contains($name, 'pendapatan')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKtaPendapatan($detail);
                } elseif (str_contains($name, 'riwayat pinjaman')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKtaRiwayatPinjaman($context);
                } elseif (str_contains($name, 'usia')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKtaUsia($context);
                } elseif (str_contains($name, 'stabilitas pekerjaan')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKtaStabilitasPekerjaan($detail);
                } elseif (str_contains($name, 'beban cicilan')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKtaBebanCicilan($detail);
                }
            } else {
                if (str_contains($name, 'lama usaha')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKurLamaUsaha($detail);
                } elseif (str_contains($name, 'omzet usaha')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKurOmzet($detail);
                } elseif (str_contains($name, 'laba bersih')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKurLabaBersih($detail);
                } elseif (str_contains($name, 'legalitas usaha')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKurLegalitas($detail);
                } elseif (str_contains($name, 'jumlah karyawan')) {
                    $scoreMap[(int) $row['id']] = $this->scoreKurJumlahKaryawan($detail);
                }
            }
        }

        if (empty($scoreMap)) {
            return false;
        }

        foreach ($scoreMap as $kriteria_id => $nilai) {
            $stmt = $this->conn->prepare(
                'INSERT INTO penilaian (pengajuan_id, kriteria_id, nilai)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
            );
            $stmt->bind_param('iid', $pengajuan_id, $kriteria_id, $nilai);
            $stmt->execute();
        }

        return true;
    }

    private function getApplicationType($pengajuan_id) {
        $stmt = $this->conn->prepare('SELECT jenis_kredit FROM pengajuan WHERE id = ?');
        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? $row['jenis_kredit'] : null;
    }

    public function getCriteria($jenis_kredit = null) {
        if ($jenis_kredit === null) {
            $result = $this->conn->query('SELECT * FROM kriteria ORDER BY jenis_kredit, id');
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        $sql = "SELECT * FROM kriteria
                WHERE jenis_kredit = ? OR jenis_kredit = 'BOTH'
                ORDER BY id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $jenis_kredit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getApplicationsByType($jenis_kredit) {
        $sql = "SELECT p.id, p.anggota_id, a.nama, p.jenis_kredit
                FROM pengajuan p
                JOIN anggota a ON p.anggota_id = a.id
                WHERE p.status IN ('pending', 'verified', 'accepted') AND p.jenis_kredit = ?
                ORDER BY p.created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $jenis_kredit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getNormalizationStats($jenis_kredit) {
        $sql = "SELECT n.kriteria_id, MAX(n.nilai) AS max_nilai, MIN(n.nilai) AS min_nilai
                FROM penilaian n
                JOIN pengajuan p ON p.id = n.pengajuan_id
                WHERE p.status IN ('pending', 'verified', 'accepted') AND p.jenis_kredit = ?
                GROUP BY n.kriteria_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('s', $jenis_kredit);
        $stmt->execute();
        $result = $stmt->get_result();

        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[(int) $row['kriteria_id']] = [
                'max' => (float) $row['max_nilai'],
                'min' => (float) $row['min_nilai'],
            ];
        }

        return $stats;
    }

    public function getAssessments($pengajuan_id) {
        $sql = "SELECT n.*, k.nama AS kriteria_nama, k.bobot, k.jenis, k.jenis_kredit
                FROM penilaian n
                JOIN kriteria k ON n.kriteria_id = k.id
                WHERE n.pengajuan_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function calculateSAW($pengajuan_id, $stats = null) {
        $assessments = $this->getAssessments($pengajuan_id);
        if (empty($assessments)) {
            return false;
        }

        $jenis_kredit = $this->getApplicationType($pengajuan_id);
        if (!$jenis_kredit) {
            return false;
        }

        if ($stats === null) {
            $stats = $this->getNormalizationStats($jenis_kredit);
        }

        $normalized_sum = 0.0;
        $weighted_score = 0.0;

        foreach ($assessments as $assessment) {
            $kriteria_id = (int) $assessment['kriteria_id'];
            if (!isset($stats[$kriteria_id])) {
                continue;
            }

            $nilai = (float) $assessment['nilai'];
            $bobot = (float) $assessment['bobot'];
            $jenis = $assessment['jenis'];
            $max = $stats[$kriteria_id]['max'];
            $min = $stats[$kriteria_id]['min'];

            if ($jenis === 'benefit') {
                $normalized = $max > 0 ? ($nilai / $max) : 0;
            } else {
                $normalized = $nilai > 0 ? ($min / $nilai) : 0;
            }

            $normalized_sum += $normalized;
            $weighted_score += $normalized * $bobot;
        }

        return [
            'normalisasi' => $normalized_sum,
            'skor' => $weighted_score,
            'persentase' => $weighted_score * 100,
            'kelayakan' => ($weighted_score * 100) >= $this->getEligibilityThreshold($jenis_kredit) ? 'layak' : 'tidak_layak',
            'threshold' => $this->getEligibilityThreshold($jenis_kredit),
        ];
    }

    public function calculateRanking($jenis_kredit = null) {
        $types = $jenis_kredit ? [$jenis_kredit] : ['KTA', 'KUR'];
        $all_scores = [];

        foreach ($types as $type) {
            $cleanup = $this->conn->prepare(
                'DELETE h FROM hasil_saw h
                 JOIN pengajuan p ON p.id = h.pengajuan_id
                 WHERE h.jenis_kredit = ? AND p.jenis_kredit = ?'
            );
            $cleanup->bind_param('ss', $type, $type);
            $cleanup->execute();

            $applications = $this->getApplicationsByType($type);
            foreach ($applications as $app) {
                $existingAssessments = $this->getAssessments((int) $app['id']);
                if (empty($existingAssessments)) {
                    $this->buildAutomaticAssessments((int) $app['id']);
                }
            }

            $stats = $this->getNormalizationStats($type);
            $scores = [];

            foreach ($applications as $app) {
                $score = $this->calculateSAW((int) $app['id'], $stats);
                if ($score !== false) {
                    $scores[] = [
                        'id' => (int) $app['id'],
                        'nama' => $app['nama'],
                        'jenis_kredit' => $type,
                        'normalisasi' => $score['normalisasi'],
                        'score' => $score['skor'],
                    ];
                }
            }

            usort($scores, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            foreach ($scores as $index => $score) {
                $ranking = $index + 1;
                $persentase = $score['score'] * 100;
                $threshold = $this->getEligibilityThreshold($type);
                $kelayakan = $persentase >= $threshold ? 'layak' : 'tidak_layak';
                $sql = "INSERT INTO hasil_saw (pengajuan_id, jenis_kredit, skor_normalisasi, skor_terbobot, persentase_saw, kelayakan, ranking)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            jenis_kredit = VALUES(jenis_kredit),
                            skor_normalisasi = VALUES(skor_normalisasi),
                            skor_terbobot = VALUES(skor_terbobot),
                            persentase_saw = VALUES(persentase_saw),
                            kelayakan = VALUES(kelayakan),
                            ranking = VALUES(ranking)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param('isdddsi', $score['id'], $score['jenis_kredit'], $score['normalisasi'], $score['score'], $persentase, $kelayakan, $ranking);
                $stmt->execute();
            }

            $all_scores = array_merge($all_scores, $scores);
        }

        return $all_scores;
    }
}
?>
