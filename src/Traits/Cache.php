<?php

namespace FredBradley\SOCSICSParser\Traits;

trait Cache
{
    /**
     * Returns the $events array from the Cache.
     * Dependant on $siteType;
     *
     * @return bool|mixed
     */
    private function fromCache()
    {
        if ($this->siteType === 'unknown') {
            return false;
        }

        if ($this->siteType === 'wordpress') {
            return get_transient($this->cacheName);
        }

        if ($this->siteType === 'laravel') {
            $output = \Illuminate\Support\Facades\Cache::get($this->cacheName);

            if ($output !== null) {
                return $output;
            }
        }

        return false;
    }
}
