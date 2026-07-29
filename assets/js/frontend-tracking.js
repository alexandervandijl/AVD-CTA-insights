(function() {
    if (!window.AVDUberCTA) {
        return;
    }

    var sessionId = getSessionId();
    var pageViewSent = false;
    var engagedSent = false;
    var scroll25Sent = false;
    var scroll50Sent = false;
    var scroll75Sent = false;
    var popupShownSent = false;

    function getSessionId() {
        try {
            var key = "avd_uber_session_id";
            var existing = localStorage.getItem(key);

            if (existing) {
                return existing;
            }

            var id = "s_" + Date.now() + "_" + Math.random().toString(16).slice(2);
            localStorage.setItem(key, id);

            return id;
        } catch (e) {
            return "s_no_storage_" + Date.now();
        }
    }

    function getDevice() {
        var w = window.innerWidth || document.documentElement.clientWidth || 0;

        if (w <= 767) {
            return "mobiel";
        }

        if (w <= 1024) {
            return "tablet";
        }

        return "desktop";
    }

    function getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || "";
        } catch (e) {
            return "";
        }
    }

    function sendEvent(data) {
        try {
            var form = new FormData();

            form.append("action", AVDUberCTA.action);
            form.append("nonce", AVDUberCTA.nonce);
            form.append("type", data.type || "unknown");
            form.append("source", data.source || "unknown");
            form.append("context", data.context || AVDUberCTA.pageType || "unknown");
            form.append("device", getDevice());
            form.append("pageUrl", AVDUberCTA.pageUrl || window.location.href);
            form.append("targetUrl", data.targetUrl || "");
            form.append("label", data.label || "");
            form.append("sessionId", sessionId);

            form.append("referrer", document.referrer || "");
            form.append("language", navigator.language || "");
            form.append(
                "screenWidth",
                window.screen && window.screen.width
                    ? window.screen.width
                    : 0
            );
            form.append(
                "screenHeight",
                window.screen && window.screen.height
                    ? window.screen.height
                    : 0
            );
            form.append("timezone", getTimezone());

            if (navigator.sendBeacon) {
                navigator.sendBeacon(AVDUberCTA.ajaxUrl, form);
                return;
            }

            fetch(AVDUberCTA.ajaxUrl, {
                method: "POST",
                body: form,
                credentials: "same-origin",
                keepalive: true
            }).catch(function() {});
        } catch (e) {}
    }

    function trackPageView() {
        if (pageViewSent) {
            return;
        }

        pageViewSent = true;

        sendEvent({
            type: "page_view",
            source: "page",
            context: AVDUberCTA.pageType || "unknown",
            label: document.title || "Pagina bekeken"
        });
    }

    function trackEngagedSession(source) {
        if (engagedSent) {
            return;
        }

        engagedSent = true;

        sendEvent({
            type: "engaged_session",
            source: source || "engagement",
            context: AVDUberCTA.pageType || "unknown",
            label: "Betrokken sessie"
        });
    }

    function trackScroll() {
        var doc = document.documentElement;
        var body = document.body;

        var scrollTop =
            window.pageYOffset ||
            doc.scrollTop ||
            body.scrollTop ||
            0;

        var scrollHeight = Math.max(
            body.scrollHeight || 0,
            doc.scrollHeight || 0,
            body.offsetHeight || 0,
            doc.offsetHeight || 0,
            body.clientHeight || 0,
            doc.clientHeight || 0
        );

        var windowHeight = window.innerHeight || doc.clientHeight || 0;
        var maxScroll = scrollHeight - windowHeight;

        if (maxScroll <= 0) {
            return;
        }

        var percent = Math.round((scrollTop / maxScroll) * 100);

        if (percent >= 25 && !scroll25Sent) {
            scroll25Sent = true;

            sendEvent({
                type: "scroll_25",
                source: "scroll",
                context: AVDUberCTA.pageType || "unknown",
                label: "Scroll 25%"
            });
        }

        if (percent >= 50 && !scroll50Sent) {
            scroll50Sent = true;

            trackEngagedSession("scroll_50");

            sendEvent({
                type: "scroll_50",
                source: "scroll",
                context: AVDUberCTA.pageType || "unknown",
                label: "Scroll 50%"
            });
        }

        if (percent >= 75 && !scroll75Sent) {
            scroll75Sent = true;

            sendEvent({
                type: "scroll_75",
                source: "scroll",
                context: AVDUberCTA.pageType || "unknown",
                label: "Scroll 75%"
            });
        }
    }

    function closestCTA(el) {
        if (!el || !el.closest) {
            return null;
        }

        var explicit = el.closest("[data-avd-cta='1']");

        if (explicit) {
            return buildCTAData(explicit, true);
        }

        var clickable = el.closest(
            "a[href], button, [role='button']"
        );

        if (!clickable) {
            return null;
        }

        return buildCTAData(clickable, false);
    }

    function buildCTAData(el, explicit) {
        if (!el) {
            return null;
        }

        if (
            el.matches(".avd-cta-close") ||
            el.matches("[data-avd-popup-close]") ||
            el.closest("[data-avd-popup-close]")
        ) {
            return null;
        }

        var href = "";

        if (el.getAttribute) {
            href = el.getAttribute("href") || "";
        }

        if (!href && el.href) {
            href = el.href;
        }

        var absoluteHref = "";

        try {
            if (href) {
                absoluteHref = new URL(
                    href,
                    window.location.href
                ).href;
            }
        } catch (e) {
            absoluteHref = href;
        }

        var text = (el.textContent || "")
            .replace(/\s+/g, " ")
            .trim();

        var className = el.className
            ? String(el.className)
            : "";

        var lowerHref = String(
            href || absoluteHref || ""
        ).toLowerCase();

        var lowerText = text.toLowerCase();
        var lowerClass = className.toLowerCase();

        if (explicit) {
            return {
                element: el,
                type:
                    el.getAttribute("data-avd-cta-type") ||
                    "cta_click",
                source:
                    el.getAttribute("data-avd-cta-source") ||
                    "explicit_cta",
                targetUrl: absoluteHref || href || "",
                label: text || "CTA"
            };
        }

        if (lowerHref.indexOf("tel:") === 0) {
            return {
                element: el,
                type: "tel_click",
                source: "phone_link",
                targetUrl: href,
                label: text || href
            };
        }

        if (lowerHref.indexOf("mailto:") === 0) {
            return {
                element: el,
                type: "mail_click",
                source: "email_link",
                targetUrl: href,
                label: text || href
            };
        }

        if (
            lowerHref.indexOf("wa.me/") !== -1 ||
            lowerHref.indexOf("whatsapp") !== -1
        ) {
            return {
                element: el,
                type: "whatsapp_click",
                source: "whatsapp_link",
                targetUrl: absoluteHref || href,
                label: text || "WhatsApp"
            };
        }

        if (
            lowerHref.indexOf(
                "/avd-updates/avd-cta-insights.zip"
            ) !== -1 ||
            lowerHref.indexOf(
                "avd-cta-insights.zip"
            ) !== -1
        ) {
            return {
                element: el,
                type: "download_click",
                source: "plugin_download",
                targetUrl: absoluteHref || href,
                label: text || "Download plugin"
            };
        }

        if (
            lowerHref.indexOf(
                "link.vraagalex.com/tikkie"
            ) !== -1 ||
            lowerHref.indexOf(
                "link.vraagalex.com/paypal"
            ) !== -1
        ) {
            return {
                element: el,
                type: "donation_click",
                source: "donation",
                targetUrl: absoluteHref || href,
                label: text || "Donatie"
            };
        }

        if (
            lowerHref.indexOf(
                "gratis-doorverbinder-app"
            ) !== -1
        ) {
            return {
                element: el,
                type: "app_click",
                source: "doorverbinder_app",
                targetUrl: absoluteHref || href,
                label: text || "Doorverbinder app"
            };
        }

        if (lowerHref.indexOf("ai-assistent") !== -1) {
            return {
                element: el,
                type: "ai_assistent_click",
                source: "ai_assistent",
                targetUrl: absoluteHref || href,
                label: text || "AI assistent"
            };
        }

        if (
            lowerHref.indexOf(
                "avd-cta-insights"
            ) !== -1
        ) {
            return {
                element: el,
                type: "plugin_page_click",
                source: "plugin_page",
                targetUrl: absoluteHref || href,
                label: text || "WordPress plugin"
            };
        }

        if (
            lowerHref.indexOf(
                "bedrijfspagina-claimen"
            ) !== -1 ||
            lowerHref.indexOf(
                "bereikbaarheidscheck"
            ) !== -1 ||
            lowerHref.indexOf(
                "bedrijfsscan"
            ) !== -1
        ) {
            return {
                element: el,
                type: "lead_click",
                source: "business_lead",
                targetUrl: absoluteHref || href,
                label: text || "Lead"
            };
        }

        if (
            lowerText.indexOf("download") !== -1 ||
            lowerText.indexOf(
                "gratis meekijken"
            ) !== -1 ||
            lowerText.indexOf(
                "conversiescan"
            ) !== -1 ||
            lowerText.indexOf("vraag") !== -1 ||
            lowerText.indexOf("bel") !== -1 ||
            lowerText.indexOf("open") !== -1 ||
            lowerText.indexOf("bekijk") !== -1 ||
            lowerClass.indexOf(
                "wp-block-button__link"
            ) !== -1 ||
            lowerClass.indexOf("button") !== -1 ||
            lowerClass.indexOf("btn") !== -1
        ) {
            return {
                element: el,
                type: "cta_click",
                source: "auto_detected_button",
                targetUrl: absoluteHref || href,
                label:
                    text ||
                    "Automatisch herkende CTA"
            };
        }

        return null;
    }

    function trackCTAClick(cta) {
        if (!cta) {
            return;
        }

        sendEvent({
            type: cta.type || "cta_click",
            source: cta.source || "auto_detected",
            context: AVDUberCTA.pageType || "unknown",
            targetUrl: cta.targetUrl || "",
            label: cta.label || ""
        });
    }

    function setupCTATracking() {
        document.addEventListener(
            "click",
            function(event) {
                var cta = closestCTA(event.target);

                if (!cta) {
                    return;
                }

                trackCTAClick(cta);
            },
            true
        );
    }

    function setupPopupTracking() {
        var popup = document.getElementById(
            "avdUberPopup"
        );

        if (!popup) {
            return;
        }

        var observer = new MutationObserver(
            function() {
                var visible =
                    popup.getAttribute(
                        "aria-hidden"
                    ) === "false" ||
                    popup.classList.contains(
                        "is-visible"
                    ) ||
                    popup.classList.contains(
                        "active"
                    );

                if (visible && !popupShownSent) {
                    popupShownSent = true;

                    sendEvent({
                        type: "popup_shown",
                        source: "popup",
                        context:
                            AVDUberCTA.pageType ||
                            "unknown",
                        label: "Popup getoond"
                    });
                }
            }
        );

        observer.observe(popup, {
            attributes: true,
            attributeFilter: [
                "class",
                "aria-hidden"
            ]
        });

        document.addEventListener(
            "click",
            function(event) {
                var close =
                    event.target &&
                    event.target.closest
                        ? event.target.closest(
                              "[data-avd-popup-close]"
                          )
                        : null;

                if (!close) {
                    return;
                }

                sendEvent({
                    type: "popup_close",
                    source: "popup",
                    context:
                        AVDUberCTA.pageType ||
                        "unknown",
                    label: "Popup gesloten"
                });
            },
            true
        );
    }

    function setupCloseTracking() {
        document.addEventListener(
            "click",
            function(event) {
                var close =
                    event.target &&
                    event.target.closest
                        ? event.target.closest(
                              ".avd-cta-close"
                          )
                        : null;

                if (!close) {
                    return;
                }

                sendEvent({
                    type: "sticky_close",
                    source: "sticky_bar",
                    context:
                        AVDUberCTA.pageType ||
                        "unknown",
                    label: "Sticky bar gesloten"
                });
            },
            true
        );
    }

    function setupEngagementTracking() {
        setTimeout(function() {
            trackEngagedSession(
                "engagement_10s"
            );
        }, 10000);

        window.addEventListener(
            "scroll",
            trackScroll,
            { passive: true }
        );

        document.addEventListener(
            "click",
            function() {
                trackEngagedSession(
                    "first_click"
                );
            },
            {
                once: true,
                capture: true
            }
        );
    }

    function init() {
        if (
            String(AVDUberCTA.trackViews) === "1"
        ) {
            trackPageView();
        }

        setupCTATracking();
        setupPopupTracking();
        setupCloseTracking();
        setupEngagementTracking();
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            init
        );
    } else {
        init();
    }
})();
