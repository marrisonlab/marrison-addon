document.addEventListener('DOMContentLoaded', function() {
    var copyButton = document.getElementById('marrison-browser-cache-copy-nginx');
    var copyStatus = document.getElementById('marrison-browser-cache-copy-status');

    if (!copyButton) {
        return;
    }

    copyButton.addEventListener('click', function() {
        var targetId = copyButton.getAttribute('data-target');
        var target = targetId ? document.getElementById(targetId) : null;

        if (!target) {
            return;
        }

        var value = target.value;

        function markCopied() {
            if (copyStatus) {
                copyStatus.textContent = 'Configurazione copiata.';
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(markCopied).catch(function() {
                target.focus();
                target.select();
                document.execCommand('copy');
                markCopied();
            });
            return;
        }

        target.focus();
        target.select();
        document.execCommand('copy');
        markCopied();
    });
});
