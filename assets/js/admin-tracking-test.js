(function () {
    document.addEventListener('click', function (event) {
        var button = event.target && event.target.closest
            ? event.target.closest('#avd-test-tracking')
            : null;

        if (!button || typeof AVDCTAITrackingTest === 'undefined') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        var result = document.getElementById('avd-test-tracking-result');

        if (result) {
            result.textContent = ' Bezig...';
        }

        var body = new URLSearchParams({
            action: AVDCTAITrackingTest.action,
            nonce: AVDCTAITrackingTest.nonce,
            type: 'admin_tracking_test',
            source: 'admin_test',
            context: 'admin_test',
            device: 'desktop',
            pageUrl: window.location.href,
            targetUrl: '',
            label: 'Handmatige trackingtest',
            sessionId: 'admin-test-' + Date.now()
        });

        fetch(AVDCTAITrackingTest.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (result) {
                result.textContent = data && data.success
                    ? ' Gelukt ✅'
                    : ' Mislukt ❌';
            }
        })
        .catch(function () {
            if (result) {
                result.textContent = ' Fout ❌';
            }
        });
    }, true);
})();
