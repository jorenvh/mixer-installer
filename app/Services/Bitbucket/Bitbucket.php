<?php

namespace App\Services\Bitbucket;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;

class Bitbucket
{
    /**
     * Download the repo in the specified version.
     *
     * @param string $version
     * @return bool
     */
    public function downloadRepo($version): bool
    {
        $endpoint = config('bitbucket.vendor') . '/' . config('bitbucket.repo') . "/get/$version.zip";

        $guzzleClient = new Client(['base_uri' => config('bitbucket.base_url')]);
        $statusCode = $guzzleClient
            ->get($endpoint, [
                'save_to' => storage_path(config('bitbucket.repo').'.zip'),
                'auth' => [config('bitbucket.username'), config('bitbucket.password')]
            ])
            ->getStatusCode();

        if ($statusCode !== 200) {
            return false;
        }

        return true;
    }

    /**
     * Check if the specified version is valid.
     *
     * @param string $version
     * @return bool
     */
    public function isValidVersion($version): bool
    {
        return $this->getAllVersions()->map(function ($version) {
            return $version['name'];
        })->contains($version);
    }

    /**
     * Get all tagged versions for the repository.
     *
     * @return Collection
     */
    public function getAllVersions(): Collection
    {
        $guzzleClient = new Client(['base_uri' => config('bitbucket.api_base_url')]);
        $response = $guzzleClient
            ->get("2.0/repositories/" . config('bitbucket.vendor') . "/" . config('bitbucket.repo') . "/refs/tags", [
                'auth' => [config('bitbucket.username'), config('bitbucket.password')]
            ])->getBody();

        return collect(\GuzzleHttp\json_decode($response)->values);
    }

    /**
     * Get the latest version name.
     *
     * @return string
     */
    public function getLatestVersionName(): string
    {
        return $this->getAllVersions()
            ->sortByDesc(function ($tag) {
                return date('Y-m-d H:i:s', strtotime($tag->date));
            })->first()->name;
    }
}