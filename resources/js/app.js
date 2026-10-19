import './bootstrap';

const recoveryKey = 'sipatu-livewire-recovery';

function showRequestError() {
    if (document.querySelector('[data-tu-request-error]')) {
        return;
    }

    const alert = document.createElement('div');
    alert.dataset.tuRequestError = '';
    alert.className = 'tu-request-error';
    alert.setAttribute('role', 'alert');
    alert.innerHTML = `
        <span>Data belum berhasil dimuat.</span>
        <button type="button">Muat ulang halaman</button>
    `;
    alert.querySelector('button').addEventListener('click', () => window.location.reload());
    document.body.append(alert);
}

function recoverFromFailedRequest(reason) {
    const now = Date.now();
    let previousRecovery = null;

    try {
        previousRecovery = JSON.parse(sessionStorage.getItem(recoveryKey));
    } catch (error) {
        // Session storage bersifat opsional; pemulihan tetap berjalan tanpa storage.
    }

    const sameRecentFailure = previousRecovery?.path === window.location.pathname
        && (now - previousRecovery.timestamp) < 30_000;

    if (sameRecentFailure) {
        showRequestError();
        return;
    }

    try {
        sessionStorage.setItem(recoveryKey, JSON.stringify({
            path: window.location.pathname,
            reason,
            timestamp: now,
        }));
    } catch (error) {
        // Browser dapat menolak session storage; reload satu kali tetap aman.
    }

    window.location.reload();
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ respond, succeed, fail }) => {
        const requestWatchdog = window.setTimeout(
            () => recoverFromFailedRequest('timeout'),
            20_000,
        );

        const finish = () => window.clearTimeout(requestWatchdog);

        respond(finish);
        succeed(() => {
            finish();
            document.querySelector('[data-tu-request-error]')?.remove();
        });
        fail(({ status, preventDefault }) => {
            finish();

            if (status === 419 || status >= 500) {
                preventDefault();
                recoverFromFailedRequest(`http-${status}`);
            }
        });
    });
});
