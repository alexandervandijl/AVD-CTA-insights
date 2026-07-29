<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class AVDCTAI_Widget {

    abstract public function render(array $payload): void;
}
