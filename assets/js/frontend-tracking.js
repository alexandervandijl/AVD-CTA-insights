(function () {
    "use strict";

    var config = window.AVDCTAIFrontend || window.AVDUberCTA || null;

    if (!config || !config.ajaxUrl || !config.action || !config.nonce) {
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
            var key = "avdctai_session_id";
            var legacyKey = "avd_uber_session_id";
            var existing = localStorage.getItem(key) || localStorage.getItem(legacyKey);

            if (existing) {
                localStorage.setItem(key, existing);
                return existing;
            }

            var id = "s_" + Date.now() + "_" + Math.random().toString(16).slice(2);
            localStorage.setItem(key, id);

            return id;
        } catch (error) {
            return "s_no_storage_" + Date.now();
        }
    }

    function getDevice() {
        var width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (width <= 767) {
            return "mobiel";
        }

        if (width <= 1024) {
            return "tablet";
        }

        return "desktop";
    }

    function getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || "";
        } catch (error) {
            return "";
        }
    }

    function getPageContext() {
        return config.pageType || "unknown";
    }

    function getPageUrl() {
        return config.pageUrl || window.location.href;
    }

    function appendEventFields(form, data) {
        form.append("action", config.action);
        form.append("nonce", config.nonce);
        form.append("type", data.type || "unknown");
        form.append("source", data.source || "unknown");
        form.append("context", data.context || getPageContext());
        form.append("device", getDevice());
        form.append("pageUrl", getPageUrl());
        form.append("targetUrl", data.targetUrl || "");
        form.append("label", data.label || "");
        form.append("sessionId", sessionId);
        form.append("referrer", document.referrer || "");
        form.append("language", navigator.language || "");
        form.append(
            "screenWidth",
            window.screen && window.screen.width ? window.screen.width : 0
        );
        form.append(
            "screenHeight",
            window.screen && window.screen.height ? window.screen.height : 0
        );
        form.append("timezone", getTimezone());
    }

    function sendEvent(data) {
        try {
            var form = new FormData();
            appendEventFields(form, data || {});

            if (navigator.sendBeacon) {
                var queued = navigator.sendBeacon(config.ajaxUrl, form);

                if (queued) {
                    return;
                }
            }

            if (window.fetch) {
                fetch(config.ajaxUrl, {
                    method: "POST",
                    body: form,
                    credentials: "same-origin",
                    keepalive: true
                }).catch(function () {});
            }
        } catch (error) {
            // Tracking mag de website nooit blokkeren.
        }
    }

    function trackPageView() {
        if (pageViewSent) {
            return;
        }

        pageViewSent = true;

        sendEvent({
            type: "page_view",
            source: "page",
            context: getPageContext(),
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
            context: getPageContext(),
            label: "Betrokken sessie"
        });
    }

    function trackScroll() {
        var doc = document.documentElement;
        var body = document.body;
        var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
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
                context: getPageContext(),
                label: "Scroll 25%"
            });
        }

        if (percent >= 50 && !scroll50Sent) {
            scroll50Sent = true;
            trackEngagedSession("scroll_50");
            sendEvent({
                type: "scroll_50",
                source: "scroll",
                context: getPageContext(),
                label: "Scroll 50%"
            });
        }

        if (percent >= 75 && !scroll75Sent) {
            scroll75Sent = true;
            sendEvent({
                type: "scroll_75",
                source: "scroll",
                context: getPageContext(),
                label: "Scroll 75%"
            });
        }
    }

    function closestCTA(element) {
        if (!element || !element.closest) {
            return null;
        }

        var explicit = element.closest("[data-avd-cta='1'], [data-avdctai-cta='1']");

        if (explicit) {
            return buildCTAData(explicit, true);
        }

        var clickable = element.closest("a[href], button, input[type='submit'], [role='button']");

        if (!clickable) {
            return null;
        }

        return buildCTAData(clickable, false);
    }

    function getElementText(element) {
        var text = element.textContent || element.value || element.getAttribute("aria-label") || "";

        return String(text).replace(/\s+/g, " ").trim();
    }

    function getAbsoluteUrl(href) {
        if (!href) {
            return "";
        }

        try {
            return new URL(href, window.location.href).href;
        } catch (error) {
            return href;
        }
    }

    function isIgnoredControl(element) {
        return Boolean(
            element.matches(".avd-cta-close, [data-avd-popup-close], [data-avdctai-popup-close]") ||
            element.closest("[data-avd-popup-close], [data-avdctai-popup-close]")
        );
    }

    function buildCTAData(element, explicit) {
        if (!element || isIgnoredControl(element)) {
            return null;
        }

        var href = element.getAttribute ? element.getAttribute("href") || "" : "";
        var absoluteHref = getAbsoluteUrl(href || element.href || "");
        var text = getElementText(element);
        var className = element.className ? String(element.className) : "";
        var lowerHref = String(href || absoluteHref || "").toLowerCase();
        var lowerText = text.toLowerCase();
        var lowerClass = className.toLowerCase();

        if (explicit) {
            return {
                element: element,
                type:
                    element.getAttribute("data-avd-cta-type") ||
                    element.getAttribute("data-avdctai-cta-type") ||
                    "cta_click",
                source:
                    element.getAttribute("data-avd-cta-source") ||
                    element.getAttribute("data-avdctai-cta-source") ||
                    "explicit_cta",
                targetUrl: absoluteHref || href || "",
                label: text || "CTA"
            };
        }

        if (lowerHref.indexOf("tel:") === 0) {
            return {
                element: element,
                type: "tel_click",
                source: "phone_link",
                targetUrl: href,
                label: text || href
            };
        }

        if (lowerHref.indexOf("mailto:") === 0) {
            return {
                element: element,
                type: "mail_click",
                source: "email_link",
                targetUrl: href,
                label: text || href
            };
        }

        if (
            lowerHref.indexOf("wa.me/") !== -1 ||
            lowerHref.indexOf("whatsapp.com/") !== -1 ||
            lowerHref.indexOf("whatsapp://") === 0
        ) {
            return {
                element: element,
                type: "whatsapp_click",
                source: "whatsapp_link",
                targetUrl: absoluteHref || href,
                label: text || "WhatsApp"
            };
        }

        if (
            element.hasAttribute("download") ||
            /\.(zip|pdf|docx?|xlsx?|csv|epub)(\?|#|$)/i.test(lowerHref) ||
            lowerText.indexOf("download") !== -1
        ) {
            return {
                element: element,
                type: "download_click",
                source: "download_link",
                targetUrl: absoluteHref || href,
                label: text || "Download"
            };
        }

        if (
            element.matches("button, input[type='submit'], [role='button']") ||
            lowerClass.indexOf("wp-block-button__link") !== -1 ||
            lowerClass.indexOf("button") !== -1 ||
            lowerClass.indexOf("btn") !== -1 ||
            lowerText.indexOf("aanvragen") !== -1 ||
            lowerText.indexOf("bestellen") !== -1 ||
            lowerText.indexOf("boeken") !== -1 ||
            lowerText.indexOf("contact") !== -1 ||
            lowerText.indexOf("offerte") !== -1 ||
            lowerText.indexOf("inschrijven") !== -1 ||
            lowerText.indexOf("registreren") !== -1 ||
            lowerText.indexOf("bel") !== -1 ||
            lowerText.indexOf("bekijk") !== -1 ||
            lowerText.indexOf("start") !== -1 ||
            lowerText.indexOf("open") !== -1
        ) {
            return {
                element: element,
                type: "cta_click",
                source: "auto_detected_button",
                targetUrl: absoluteHref || href,
                label: text || "Automatisch herkende CTA"
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
            context: getPageContext(),
            targetUrl: cta.targetUrl || "",
            label: cta.label || ""
        });
    }

    function setupCTATracking() {
        document.addEventListener(
            "click",
            function (event) {
                var cta = closestCTA(event.target);

                if (cta) {
                    trackCTAClick(cta);
                }
            },
            true
        );
    }

    function findPopup() {
        return (
            document.getElementById("avdctaiPopup") ||
            document.getElementById("avdUberPopup") ||
            document.querySelector("[data-avdctai-popup], [data-avd-popup]")
        );
    }

    function popupIsVisible(popup) {
        if (!popup) {
            return false;
        }

        return Boolean(
            popup.getAttribute("aria-hidden") === "false" ||
            popup.classList.contains("is-visible") ||
            popup.classList.contains("active") ||
            popup.classList.contains("open")
        );
    }

    function trackPopupShown(popup) {
        if (!popupShownSent && popupIsVisible(popup)) {
            popupShownSent = true;
            sendEvent({
                type: "popup_shown",
                source: "popup",
                context: getPageContext(),
                label: "Popup getoond"
            });
        }
    }

    function setupPopupTracking() {
        var popup = findPopup();

        if (popup && window.MutationObserver) {
            trackPopupShown(popup);

            var observer = new MutationObserver(function () {
                trackPopupShown(popup);
            });

            observer.observe(popup, {
                attributes: true,
                attributeFilter: ["class", "aria-hidden", "style"]
            });
        }

        document.addEventListener(
            "click",
            function (event) {
                var close =
                    event.target && event.target.closest
                        ? event.target.closest(
                              "[data-avd-popup-close], [data-avdctai-popup-close]"
                          )
                        : null;

                if (!close) {
                    return;
                }

                sendEvent({
                    type: "popup_close",
                    source: "popup",
                    context: getPageContext(),
                    label: "Popup gesloten"
                });
            },
            true
        );
    }

    function setupCloseTracking() {
        document.addEventListener(
            "click",
            function (event) {
                var close =
                    event.target && event.target.closest
                        ? event.target.closest(".avd-cta-close, .avdctai-cta-close")
                        : null;

                if (!close) {
                    return;
                }

                sendEvent({
                    type: "sticky_close",
                    source: "sticky_bar",
                    context: getPageContext(),
                    label: "Sticky bar gesloten"
                });
            },
            true
        );
    }

    function setupEngagementTracking() {
        window.setTimeout(function () {
            trackEngagedSession("engagement_10s");
        }, 10000);

        window.addEventListener("scroll", trackScroll, { passive: true });

        document.addEventListener(
            "click",
            function () {
                trackEngagedSession("first_click");
            },
            {
                once: true,
                capture: true
            }
        );
    }

    function init() {
        if (String(config.trackViews) === "1" || config.trackViews === true) {
            trackPageView();
        }

        setupCTATracking();
        setupPopupTracking();
        setupCloseTracking();
        setupEngagementTracking();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
