(function () {
    "use strict";

    var config = window.AVDCTAIDashboard || null;
    var refreshInterval = 10000;
    var requestInProgress = false;
    var timer = null;

    function dispatchRefresh(detail) {
        document.dispatchEvent(
            new CustomEvent("avd-dashboard-refresh", {
                detail: detail || {}
            })
        );
    }

    function refreshDashboard() {
        if (
            !config ||
            typeof config.ajaxUrl !== "string" ||
            config.ajaxUrl === "" ||
            typeof config.nonce !== "string" ||
            config.nonce === "" ||
            requestInProgress
        ) {
            return;
        }

        requestInProgress = true;

        fetch(config.ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                action: "avd_uber_cta_dashboard_stats",
                nonce: config.nonce
            }).toString()
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Dashboard request failed.");
                }

                return response.json();
            })
            .then(function (data) {
                if (!data || data.success !== true) {
                    return;
                }

                dispatchRefresh(data.data);
            })
            .catch(function () {
                // De bestaande dashboardweergave blijft intact bij een tijdelijke fout.
            })
            .finally(function () {
                requestInProgress = false;
            });
    }

    function startAutoRefresh() {
        refreshDashboard();

        if (timer !== null) {
            window.clearInterval(timer);
        }

        timer = window.setInterval(refreshDashboard, refreshInterval);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", startAutoRefresh, {
            once: true
        });
    } else {
        startAutoRefresh();
    }

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            refreshDashboard();
        }
    });
})();
