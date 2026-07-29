(function () {
    function refreshDashboard() {
        if (typeof ajaxurl === "undefined" || typeof AVDUberDashboard === "undefined") {
            return;
        }

        fetch(ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                action: "avd_uber_cta_dashboard_stats",
                nonce: AVDUberDashboard.nonce
            })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (!data.success) {
                return;
            }

            document.dispatchEvent(new CustomEvent("avd-dashboard-refresh", {
                detail: data.data
            }));
        })
        .catch(function(){});
    }

    refreshDashboard();
    setInterval(refreshDashboard, 10000);
})();
