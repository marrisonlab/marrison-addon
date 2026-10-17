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

        function copyFallback() {
            target.focus();
            target.select();
            try {
                if (document.execCommand('copy')) {
                    markCopied();
                    return;
                }
            } catch (e) {}
            if (copyStatus) {
                copyStatus.textContent = 'Copia non riuscita. Il testo è selezionato: copialo manualmente.';
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(markCopied).catch(copyFallback);
            return;
        }

        copyFallback();
    });
});
