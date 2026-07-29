<?php
/**
 * Installer en migraties voor AVD CTA Insights.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Installer {

    public static function init(): void {
        $installed = get_option('avdctai_version');

        /*
         * Eenmalige migratie vanaf de oude optionnaam.
         */
        if ($installed === false) {
            $legacy_installed = get_option('avd_uber_cta_version');

            if (is_string($legacy_installed) && $legacy_installed !== '') {
                $installed = $legacy_installed;
                update_option('avdctai_version', $legacy_installed, false);
            }
        }

        if ($installed !== AVDCTAI_Plugin::VERSION) {
            self::upgrade($installed);
            update_option(
                'avdctai_version',
                AVDCTAI_Plugin::VERSION,
                false
            );
        }
    }

    private static function upgrade($old_version): void {
        /*
         * Hier komen toekomstige database-upgrades.
         *
         * Voorbeeld:
         *
         * if (
         *     is_string($old_version) &&
         *     version_compare($old_version, '4.1.0', '<')
         * ) {
         *     self::upgrade_to_410();
         * }
         */
    }
}
