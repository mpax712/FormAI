<?php

namespace App\Infrastructure\AI;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProviderTraffic
{
    public function store(): Repository
    {
        return Cache::store(config('ai_grading.cache_store', 'file'));
    }

    private function key(array $destination): string
    {
        return 'ai:quota:'.hash('sha256', $destination['provider'].':'.$destination['quota_group']);
    }

    private function circuitKey(array $destination): string
    {
        return 'ai:circuit:'.hash('sha256', $destination['provider'].':'.$destination['quota_group'].':'.$destination['model']);
    }

    public function acquire(array $destination): array
    {
        $store = $this->store();
        $key = $this->key($destination);

        return $store->lock($key.':lock', 5)->block(2, function () use ($store, $key, $destination) {
            $now = now()->timestamp;
            $circuit = $store->get($this->circuitKey($destination), []);
            if (($circuit['until'] ?? 0) > $now) {
                return ['delay' => $circuit['until'] - $now, 'reason' => 'provider_paused'];
            }
            $state = $store->get($key, ['events' => [], 'leases' => [], 'next' => 0, 'cooldown' => 0]);
            $state['events'] = array_values(array_filter($state['events'], fn ($e) => $e[0] > $now - 86400));
            $state['leases'] = array_filter($state['leases'], fn ($until) => $until > $now);
            $delay = max(0, $state['next'] - $now, $state['cooldown'] - $now);
            $quota = $destination['quota'];
            // Reductions by the administrator apply to already queued requests too.
            $liveQuota = config('ai_grading.quotas.'.$destination['quota_group'], []);
            foreach ($quota as $limit => $value) {
                $quota[$limit] = min($value, (int) ($liveQuota[$limit] ?? 0));
                if ($quota[$limit] <= 0) {
                    return ['delay' => 300, 'reason' => 'quota_configuration'];
                }
            }
            foreach (['rpm' => 60, 'rph' => 3600, 'rpd' => 86400, 'tpm' => 60] as $limit => $seconds) {
                $events = array_values(array_filter($state['events'], fn ($e) => $e[0] > $now - $seconds));
                $capacity = (int) floor($quota[$limit] * config('ai_grading.margin'));
                $needed = $limit === 'tpm' ? $destination['reserved_tokens'] : 1;
                $used = $limit === 'tpm' ? array_sum(array_column($events, 1)) : count($events);
                if ($needed > $capacity) {
                    return ['delay' => 86400, 'reason' => 'quota_configuration'];
                }
                foreach ($events as $event) {
                    if ($used + $needed <= $capacity) {
                        break;
                    }
                    $delay = max($delay, $event[0] + $seconds - $now + 1);
                    $used -= $limit === 'tpm' ? $event[1] : 1;
                }
            }
            if (count($state['leases']) >= $quota['concurrency']) {
                $delay = max($delay, min($state['leases']) - $now + 1);
            }
            if ($delay > 0) {
                return ['delay' => $delay, 'reason' => 'rate_limit'];
            }
            $token = (string) Str::uuid();
            $state['events'][] = [$now, $destination['reserved_tokens']];
            $state['leases'][$token] = $now + config('ai_grading.lease_seconds');
            $state['next'] = $now + (int) ceil(60 / max(1, floor($quota['rpm'] * config('ai_grading.margin'))));
            $store->put($key, $state, 172800);

            return ['token' => $token];
        });
    }

    public function release(array $destination, string $token): void
    {
        $store = $this->store();
        $key = $this->key($destination);
        $store->lock($key.':lock', 5)->block(2, function () use ($store, $key, $token) {
            $state = $store->get($key);
            if ($state) {
                unset($state['leases'][$token]);
                $store->put($key, $state, 172800);
            }
        });
    }

    public function failure(array $destination, string $kind, int $delay): void
    {
        $store = $this->store();
        $key = $this->key($destination);
        if ($kind === 'rate_limit' || $kind === 'quota') {
            $store->lock($key.':lock', 5)->block(2, function () use ($store, $key, $delay) {
                $state = $store->get($key, ['events' => [], 'leases' => [], 'next' => 0, 'cooldown' => 0]);
                $state['cooldown'] = max($state['cooldown'], now()->timestamp + $delay);
                $store->put($key, $state, 172800);
            });
        }
        $circuitKey = $this->circuitKey($destination);
        $store->lock($circuitKey.':lock', 5)->block(2, function () use ($store, $circuitKey, $kind) {
            $state = $store->get($circuitKey, ['failures' => 0, 'until' => 0]);
            if ($kind === 'technical') {
                $state['failures']++;
            } else {
                $state['failures'] = 0;
            }
            if ($kind === 'unavailable' || $state['failures'] >= 3) {
                $state['until'] = now()->timestamp + 300;
            }
            $store->put($circuitKey, $state, 600);
        });
    }

    public function success(array $destination): void
    {
        $key = $this->circuitKey($destination);
        $this->store()->lock($key.':lock', 5)->block(2, fn () => $this->store()->forget($key));
    }
}
