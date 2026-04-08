<?php
namespace MongoDB\BSON {
    if (!class_exists('MongoDB\\BSON\\UTCDateTime')) {
        class UTCDateTime {
            private $ms;
            public function __construct($ms = null) { $this->ms = $ms ?? (int) round(microtime(true) * 1000); }
            public function toDateTime() {
                $sec = floor($this->ms / 1000);
                $usec = ($this->ms % 1000) * 1000;
                $dt = new \DateTime('@' . $sec);
                $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                return $dt;
            }
            public function __toString() { return (string) $this->ms; }
        }
    }
}
