<?php

namespace App\Support;

/**
 * Peta nama provinsi Indonesia ke zona waktu.
 *
 * Cuma provinsi WITA & WIT yang didaftarkan eksplisit (jumlahnya sedikit),
 * provinsi lain (Sumatra, Jawa, Kalimantan Barat/Tengah, dst — mayoritas
 * negara) otomatis dianggap WIB. Ini lebih tahan terhadap variasi penulisan
 * nama provinsi Jawa/Sumatra dibanding mendaftar satu-satu semuanya.
 */
class IndonesianTimezone
{
    protected const WITA = [
        'bali',
        'nusa tenggara barat', 'ntb',
        'nusa tenggara timur', 'ntt',
        'kalimantan selatan',
        'kalimantan timur',
        'kalimantan utara',
        'sulawesi utara',
        'sulawesi tengah',
        'sulawesi selatan',
        'sulawesi tenggara',
        'sulawesi barat',
        'gorontalo',
    ];

    protected const WIT = [
        'maluku',
        'maluku utara',
        'papua',
        'papua barat',
        'papua barat daya',
        'papua selatan',
        'papua tengah',
        'papua pegunungan',
    ];

    /**
     * @return array{zone: string, label: string} mis. ['zone' => 'Asia/Jakarta', 'label' => 'WIB']
     */
    public static function resolve(?string $provinceName): array
    {
        $normalized = strtolower(trim((string) $provinceName));

        if ($normalized !== '') {
            foreach (self::WIT as $needle) {
                if (str_contains($normalized, $needle)) {
                    return ['zone' => 'Asia/Jayapura', 'label' => 'WIT'];
                }
            }
            foreach (self::WITA as $needle) {
                if (str_contains($normalized, $needle)) {
                    return ['zone' => 'Asia/Makassar', 'label' => 'WITA'];
                }
            }
        }

        // Default: WIB (Sumatra, Jawa, Kalimantan Barat/Tengah, dan fallback
        // kalau provinsi tidak diketahui/belum diisi).
        return ['zone' => 'Asia/Jakarta', 'label' => 'WIB'];
    }
}