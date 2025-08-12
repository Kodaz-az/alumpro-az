// Progressive Web App functionality

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isStandalone = false;
        
        this.init();
    }

    init() {
        this.checkInstallStatus();
        this.setupEventListeners();
        this.registerServiceWorker();
        this.checkForUpdates();
    }

    checkInstallStatus() {
        // Check if running in standalone mode
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone ||
                           document.referrer.includes('android-app://');

        // Check if already installed
        this.isInstalled = this.isStandalone || localStorage.getItem('pwa_installed') === 'true';

        if (this.isInstalled) {
            this.hidePWAPrompt();
        }
    }

    setupEventListeners() {
        // Listen for install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showPWAPrompt();
        });

        // Listen for app installed
        window.addEventListener('appinstalled', () => {
            this.onAppInstalled();
        });

        // Install button click
        const installBtn = document.getElementById('installPWA');
        if (installBtn) {
            installBtn.addEventListener('click', () => {
                this.installApp();
            });
        }

        // Check for display mode changes
        window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
            this.isStandalone = e.matches;
            if (e.matches) {
                this.onAppInstalled();
            }
        });
    }

    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('ServiceWorker registration successful:', registration);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    this.showUpdatePrompt();
                                }
                            });
                        });
                    })
                    .catch((error) => {
                        console.log('ServiceWorker registration failed:', error);
                    });
            });
        }
    }

    showPWAPrompt() {
        if (this.isInstalled) return;

        const pwaModal = document.getElementById('pwaModal');
        if (pwaModal) {
            const modal = new bootstrap.Modal(pwaModal);
            modal.show();
        }
    }

    hidePWAPrompt() {
        const pwaModal = document.getElementById('pwaModal');
        if (pwaModal) {
            const modal = bootstrap.Modal.getInstance(pwaModal);
            if (modal) {
                modal.hide();
            }
        }
    }

    async installApp() {
        if (!this.deferredPrompt) {
            this.showManualInstallInstructions();
            return;
        }

        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            
            this.deferredPrompt = null;
        } catch (error) {
            console.error('Install prompt error:', error);
        }
        
        this.hidePWAPrompt();
    }

    onAppInstalled() {
        console.log('App was installed');
        this.isInstalled = true;
        localStorage.setItem('pwa_installed', 'true');
        this.hidePWAPrompt();
        
        // Show success message
        if (window.AlumproApp && window.AlumproApp.showNotification) {
            window.AlumproApp.showNotification(
                'Tətbiq Yükləndi!', 
                'Alumpro.Az tətbiqi uğurla telefonunuza yükləndi.', 
                'success'
            );
        }
    }

    showManualInstallInstructions() {
        const instructions = this.getInstallInstructions();
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tətbiqi Yükləyin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${instructions}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    getInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);
        
        if (isIOS) {
            return `
                <div class="text-center">
                    <i class="bi bi-phone display-4 text-primary mb-3"></i>
                    <h6>iOS üçün yükləmə:</h6>
                    <ol class="text-start">
                        <li>Safari brauzerində bu səhifəni açın</li>
                        <li>Aşağıdakı "Paylaş" düyməsini basın <i class="bi bi-share"></i></li>
                        <li>"Ana Ekrana Əlavə Et" seçin</li>
                        <li>"Əlavə Et" düyməsini basın</li>
                    </ol>
                </div>
            `;
        } else if (isAndroid) {
            return `
                <div class="text-center">
                    <i class="bi bi-phone display-4 text-success mb-3"></i>
                    <h6>Android üçün yükləmə:</h6>
                    <ol class="text-start">
                        <li>Chrome brauzerində bu səhifəni açın</li>
                        <li>Menyudan "Ana ekrana əlavə et" seçin</li>
                        <li>Və ya üst hissədə görünən "Yüklə" düyməsini basın</li>
                    </ol>
                </div>
            `;
        } else {
            return `
                <div class="text-center">
                    <i class="bi bi-laptop display-4 text-info mb-3"></i>
                    <h6>Desktop üçün yükləmə:</h6>
                    <p>Chrome və ya Edge brauzerlərində ünvan çubuğunun yanındakı "Yüklə" ikonunu basın.</p>
                </div>
            `;
        }
    }

    showUpdatePrompt() {
        const updateModal = document.createElement('div');
        updateModal.className = 'modal fade';
        updateModal.innerHTML = `
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-arrow-clockwise"></i> Yeniləmə
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <i class="bi bi-download display-4 text-info mb-3"></i>
                        <p>Yeni versiya mövcuddur. Yeniləmək istəyirsiniz?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Sonra</button>
                        <button type="button" class="btn btn-info btn-sm" onclick="window.location.reload()">Yenilə</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(updateModal);
        const modal = new bootstrap.Modal(updateModal);
        modal.show();
        
        updateModal.addEventListener('hidden.bs.modal', () => {
            updateModal.remove();
        });
    }

    checkForUpdates() {
        // Check for updates every 30 minutes
        setInterval(() => {
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ command: 'checkForUpdates' });
            }
        }, 30 * 60 * 1000);
    }

    // Utility methods
    isAppInstalled() {
        return this.isInstalled;
    }

    isRunningStandalone() {
        return this.isStandalone;
    }

    // Network status
    setupNetworkStatus() {
        const updateNetworkStatus = () => {
            const status = navigator.onLine ? 'online' : 'offline';
            document.body.setAttribute('data-network-status', status);
            
            if (!navigator.onLine) {
                this.showOfflineMessage();
            }
        };

        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();
    }

    showOfflineMessage() {
        if (window.AlumproApp && window.AlumproApp.showNotification) {
            window.AlumproApp.showNotification(
                'Offline Rejim', 
                'İnternet əlaqəniz yoxdur. Bəzi funksiyalar məhdud ola bilər.', 
                'warning'
            );
        }
    }
}

// Initialize PWA manager
document.addEventListener('DOMContentLoaded', function() {
    window.pwaManager = new PWAManager();
});

// Handle iOS special case
if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
    // Add iOS specific handling
    document.addEventListener('DOMContentLoaded', function() {
        // Show install prompt for iOS after 30 seconds
        setTimeout(() => {
            if (!window.pwaManager.isAppInstalled() && !localStorage.getItem('ios_install_prompted')) {
                window.pwaManager.showPWAPrompt();
                localStorage.setItem('ios_install_prompted', 'true');
            }
        }, 30000);
    });
}