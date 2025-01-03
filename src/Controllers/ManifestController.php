<?php

namespace Beneflic\ExpoUpdates\Controllers;

use Beneflic\ExpoUpdates\ExpoUpdate;
use GuzzleHttp\Psr7\MultipartStream;
use Illuminate\Http\Request;
use Illuminate\Http\Testing\MimeType;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ManifestController extends Controller
{
    public function __invoke(Request $request)
    {
        $channel = $request->header('expo-channel-name');
        $runtimeVersion = $request->header('expo-runtime-version');
        $platform = $request->header('expo-platform');
        $currentUpdateId = $request->header('expo-update-id');
        $protocolVersion = $request->header('expo-protocol-version');
        $expectSignature = $request->header('expo-expect-signature', false);

        $cacheKey = "expo-updates:$channel,$runtimeVersion,$platform,$currentUpdateId,$protocolVersion,$expectSignature";
        $response = Cache::remember($cacheKey, 60, function () use ($channel, $runtimeVersion, $platform, $currentUpdateId, $protocolVersion, $expectSignature) {
            return $this->getUpdate($channel, $runtimeVersion, $platform, $currentUpdateId, $protocolVersion, $expectSignature);
        });

        return response($response['body'], $response['status'])
            ->withHeaders($response['headers']);
    }

    private function getUpdate($channel, $runtimeVersion, $platform, $currentUpdateId, $protocolVersion, $expectSignature)
    {
        $update = ExpoUpdate::where([
            'channel' => $channel,
            'runtime_version' => $runtimeVersion,
        ])->orderByDesc('created_at')->first();

        if ($update === null || ($update->id === $currentUpdateId && $protocolVersion === '1')) {
            return [
                'status' => $protocolVersion === '1' ? 204 : 404,
                'content' => '',
                'headers' => [
                    'x-computed' => now()->timestamp,
                ],
            ];
        }

        if ($update->type == 'rollback') {
            $directive = [
                'type' => 'rollBackToEmbedded',
                'parameters' => [
                    'commitTime' => $update->created_at->toISOString(),
                ],
            ];
            if ($expectSignature) {
                $signature = $this->signManifest($directive);
            }
            $multipartStream = new MultipartStream([
                [
                    'name' => 'directive',
                    'contents' => json_encode($directive),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        ...($expectSignature ? ['expo-signature' => $signature] : []),
                    ],
                ],
            ]);

            return [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'multipart/form-data; boundary='.$multipartStream->getBoundary(),
                    'expo-sfv-version' => '0',
                    'cache-control' => 'private, max-age=0',
                    'expo-protocol-version' => $protocolVersion,
                    'x-computed' => now()->timestamp,
                ],
                'body' => $multipartStream->getContents(),
            ];
        }

        $platformMetadata = $update->metadata['fileMetadata'][$platform];
        $disk = Storage::disk($update->disk);
        $bundlePath = $update->directory.'/'.$platformMetadata['bundle'];
        $bundleFileContent = $disk->get($bundlePath);
        $metadataHash = hash('sha256', $bundleFileContent);
        $manifest = [
            'id' => (implode('-', [
                substr($metadataHash, 0, 8),
                substr($metadataHash, 8, 4),
                substr($metadataHash, 12, 4),
                substr($metadataHash, 16, 4),
                substr($metadataHash, 20),
            ])),
            'createdAt' => $update->created_at->toIsoString(),
            'runtimeVersion' => $update->runtime_version,
            'assets' => array_map(function ($asset) use ($update, $disk) {
                $path = $update->directory.'/'.$asset['path'];
                $fileContent = $disk->get($path);

                return [
                    'hash' => strtr(base64_encode(hash('sha256', $fileContent, true)), '+/=', '-_.'),
                    'key' => hash('md5', $fileContent),
                    'fileExtension' => '.'.$asset['ext'],
                    'contentType' => MimeType::get($asset['ext']),
                    'url' => $disk->url($path),
                ];
            }, $platformMetadata['assets']),
            'launchAsset' => [
                'hash' => strtr(base64_encode(hash('sha256', $bundleFileContent, true)), '+/=', '-_.'),
                'key' => hash('md5', $bundleFileContent),
                'contentType' => 'application/javascript',
                'url' => $disk->url($bundlePath),
            ],
            'metadata' => [],
            'extra' => [
                'expoClient' => $update['expo_config'],
            ],
        ];

        if ($expectSignature) {
            $signature = $this->signManifest($manifest);
        }

        $assetRequestHeaders = [];
        foreach ([...$manifest['assets'], $manifest['launchAsset']] as $value) {
            $assetRequestHeaders[$value['key']] = config('expo-updates.asset_request_headers', []);
        }

        $multipartStream = new MultipartStream([
            [
                'name' => 'manifest',
                'contents' => json_encode($manifest),
                'headers' => [
                    'Content-Type' => 'application/json',
                    ...($expectSignature ? ['expo-signature' => $signature] : []),
                ],
            ],
            [
                'name' => 'extensions',
                'contents' => json_encode(['assetRequestHeaders' => $assetRequestHeaders]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ],
        ]);

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary='.$multipartStream->getBoundary(),
                'expo-sfv-version' => '0',
                'cache-control' => 'private, max-age=0',
                'expo-protocol-version' => $protocolVersion,
                'x-computed' => now()->timestamp,
            ],
            'body' => $multipartStream->getContents(),
        ];
    }

    private function signManifest($manifest)
    {
        $privateKey = config('expo-updates.private_key');
        $hash = hash('sha256', json_encode($manifest));
        $hashSignature = '';
        openssl_sign($hash, $hashSignature, $privateKey, OPENSSL_ALGO_SHA256);

        return "sig=$hashSignature,keyid=main";
    }
}
