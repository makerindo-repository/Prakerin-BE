<?php

namespace App\Services;

use App\Models\CityRegency;
use App\Models\Province;
use App\Models\RegionalDataSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegionalDataSyncService
{
    protected string $baseUrl = 'https://emsifa.github.io/api-wilayah-indonesia/api';

    /**
     * Synchronize provinces and cities/regencies from external API.
     *
     * @param bool $dryRun
     * @param string $source
     * @return RegionalDataSyncLog
     */
    public function sync(bool $dryRun = false, string $source = 'emsifa'): RegionalDataSyncLog
    {
        $log = RegionalDataSyncLog::create([
            'sync_source' => $source,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        $provincesCreated = 0;
        $provincesUpdated = 0;
        $citiesCreated = 0;
        $citiesUpdated = 0;
        $errors = [];

        try {
            Log::info("Starting regional data sync from {$this->baseUrl} (dryRun: " . ($dryRun ? 'yes' : 'no') . ")");

            // 1. Fetch Provinces
            $provincesResponse = Http::timeout(30)->get("{$this->baseUrl}/provinces.json");

            if (!$provincesResponse->successful()) {
                throw new \Exception("Failed to fetch provinces: HTTP " . $provincesResponse->status());
            }

            $provincesData = $provincesResponse->json();
            if (!is_array($provincesData)) {
                throw new \Exception("Invalid provinces response payload");
            }

            Log::info("Fetched " . count($provincesData) . " provinces from API");

            foreach ($provincesData as $provItem) {
                $extId = (string) ($provItem['id'] ?? '');
                $name = trim((string) ($provItem['name'] ?? ''));

                if (!$extId || !$name) {
                    continue;
                }

                // Match by external_id first, then fallback to name match
                $province = Province::where('external_id', $extId)->first()
                    ?? Province::where('name', $name)->first();

                if ($province) {
                    if (!$dryRun) {
                        $province->update([
                            'external_id' => $extId,
                            'name' => $name,
                            'is_accepted' => true,
                            'synced_at' => now(),
                        ]);
                    }
                    $provincesUpdated++;
                } else {
                    if (!$dryRun) {
                        $province = Province::create([
                            'external_id' => $extId,
                            'name' => $name,
                            'is_accepted' => true,
                            'synced_at' => now(),
                        ]);
                    }
                    $provincesCreated++;
                }

                // 2. Fetch Regencies for this Province
                try {
                    $regenciesResponse = Http::timeout(20)->get("{$this->baseUrl}/regencies/{$extId}.json");
                    if ($regenciesResponse->successful()) {
                        $regenciesData = $regenciesResponse->json();
                        if (is_array($regenciesData)) {
                            foreach ($regenciesData as $regItem) {
                                $cityExtId = (string) ($regItem['id'] ?? '');
                                $cityName = trim((string) ($regItem['name'] ?? ''));

                                if (!$cityExtId || !$cityName) {
                                    continue;
                                }

                                $city = CityRegency::where('external_id', $cityExtId)->first();

                                if (!$city && $province && $province->id) {
                                    $city = CityRegency::where('province_id', $province->id)
                                        ->where('name', $cityName)
                                        ->first();
                                }

                                if ($city) {
                                    if (!$dryRun) {
                                        $city->update([
                                            'external_id' => $cityExtId,
                                            'province_id' => $province->id,
                                            'name' => $cityName,
                                            'is_accepted' => true,
                                            'synced_at' => now(),
                                        ]);
                                    }
                                    $citiesUpdated++;
                                } else {
                                    if (!$dryRun && $province && $province->id) {
                                        CityRegency::create([
                                            'external_id' => $cityExtId,
                                            'province_id' => $province->id,
                                            'name' => $cityName,
                                            'is_accepted' => true,
                                            'synced_at' => now(),
                                        ]);
                                    }
                                    $citiesCreated++;
                                }
                            }
                        }
                    } else {
                        $errors[] = "Failed to fetch regencies for province {$name} (ID: {$extId}): HTTP " . $regenciesResponse->status();
                    }
                } catch (Throwable $e) {
                    $errors[] = "Error fetching regencies for province {$name}: " . $e->getMessage();
                }
            }

            $log->update([
                'status' => empty($errors) ? 'success' : 'partial_success',
                'provinces_created' => $provincesCreated,
                'provinces_updated' => $provincesUpdated,
                'cities_created' => $citiesCreated,
                'cities_updated' => $citiesUpdated,
                'errors' => !empty($errors) ? implode("\n", $errors) : null,
                'completed_at' => now(),
            ]);

            Log::info("Regional sync completed. Created {$provincesCreated} provs, {$citiesCreated} cities. Updated {$provincesUpdated} provs, {$citiesUpdated} cities.");

        } catch (Throwable $e) {
            Log::error("Regional sync failed: " . $e->getMessage());
            $log->update([
                'status' => 'failed',
                'errors' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log;
    }
}
