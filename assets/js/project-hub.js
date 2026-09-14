(function () {
    "use strict";

    var config = window.AVDCTAIFrontend || window.AVDUberCTA || null;
    var selector = "[data-avdctai-project-hub='1']";

    if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
        return;
    }

    function sessionId() {
        var key = "avdctai_session_id";
        var legacyKey = "avd_uber_session_id";
        try {
            return localStorage.getItem(key) || localStorage.getItem(legacyKey) || "";
        } catch (error) {
            return "";
        }
    }

    function device() {
        var width = window.innerWidth || document.documentElement.clientWidth || 0;
        if (width <= 767) { return "mobiel"; }
        if (width <= 1024) { return "tablet"; }
        return "desktop";
    }

    function sendSeen(element) {
        if (!element || element.getAttribute("data-avdctai-project-hub-seen") === "1") {
            return;
        }

        element.setAttribute("data-avdctai-project-hub-seen", "1");

        var form = new FormData();
        form.append("action", config.action);
        form.append("nonce", config.nonce);
        form.append("type", "project_hub_seen");
        form.append("source", element.getAttribute("data-avdctai-placement") || "project_hub");
        form.append("context", config.pageType || "unknown");
        form.append("device", device());
        form.append("pageUrl", config.pageUrl || window.location.href);
        form.append("targetUrl", "");
        form.append("label", "Project Hub");
        form.append("sessionId", sessionId());
        form.append("referrer", document.referrer || "");
        form.append("language", navigator.language || "");
        form.append("screenWidth", window.screen && window.screen.width ? window.screen.width : 0);
        form.append("screenHeight", window.screen && window.screen.height ? window.screen.height : 0);

        try {
            form.append("timezone", Intl.DateTimeFormat().resolvedOptions().timeZone || "");
        } catch (error) {
            form.append("timezone", "");
        }

        try {
            if (navigator.sendBeacon && navigator.sendBeacon(config.ajaxUrl, form)) {
                return;
            }
        } catch (error) {}

        if (window.fetch) {
            fetch(config.ajaxUrl, {
                method: "POST",
                body: form,
                credentials: "same-origin",
                keepalive: true
            }).catch(function () {});
        }
    }

    function init() {
        var elements = Array.prototype.slice.call(document.querySelectorAll(selector));
        if (!elements.length) { return; }

        if (!("IntersectionObserver" in window)) {
            elements.forEach(sendSeen);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.35) { return; }
                sendSeen(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: [0.35] });

        elements.forEach(function (element) {
            observer.observe(element);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
