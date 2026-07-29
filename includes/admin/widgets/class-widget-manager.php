<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Manager {

    /**
     * @var AVDCTAI_Widget[]
     */
    private array $widgets = array();

    public function __construct() {
        $this->register_default_widgets();
    }

    private function register_default_widgets(): void {
        /*
         * Dashboard = cockpit.
         * Alleen kerncijfers, gezondheid, prioriteiten en trends.
         *
         * Money Events, Zakelijke Intentie en CTA Breakdown
         * horen voortaan bij AI Analyse en worden daarom
         * niet meer op het hoofd-dashboard getoond.
         */

        $this->register(new AVDCTAI_Widget_Today());
        $this->register(new AVDCTAI_Widget_Health());
        $this->register(new AVDCTAI_Widget_Priority());
        $this->register(new AVDCTAI_Widget_Benchmarks());
        $this->register(new AVDCTAI_Widget_Trends());
        $this->register(new AVDCTAI_Widget_Top_Pages());
        $this->register(new AVDCTAI_Widget_Visitor_Intelligence());

        /*
         * AI Coach blijft beschikbaar als compact adviesblok.
         * Verwijder deze regel later als je alle AI-content
         * uitsluitend op de pagina AI Analyse wilt tonen.
         */
        $this->register(new AVDCTAI_Widget_AI_Coach());
    }

    public function register(AVDCTAI_Widget $widget): void {
        $this->widgets[] = $widget;
    }

    public function render(array $payload): void {
        foreach ($this->widgets as $widget) {
            $widget->render($payload);
        }
    }

    public function count(): int {
        return count($this->widgets);
    }
}
