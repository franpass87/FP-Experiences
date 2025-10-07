
/**
 * FP Experiences Module Loader
 * Caricatore modulare per ottimizzare il caricamento
 */

class FpExperiencesLoader {
    constructor() {
        this.loadedModules = new Set();
        this.moduleCallbacks = new Map();
    }

    /**
     * Carica un modulo specifico
     */
    async loadModule(moduleName) {
        if (this.loadedModules.has(moduleName)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `/assets/js/dist/${moduleName}.js`;
            script.onload = () => {
                this.loadedModules.add(moduleName);
                resolve();
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Carica i moduli necessari per una pagina
     */
    async loadPageModules(pageType) {
        const modules = this.getRequiredModules(pageType);
        
        for (const module of modules) {
            await this.loadModule(module);
        }
    }

    /**
     * Determina i moduli necessari per tipo di pagina
     */
    getRequiredModules(pageType) {
        const moduleMap = {
            'admin': ['core', 'tabs', 'mediaControls', 'galleryControls', 'taxonomyEditors', 'repeaters', 'formValidation', 'calendar', 'tools'],
            'frontend': ['checkout', 'importer'],
            'listing': ['core', 'listing'],
            'calendar': ['core', 'calendar']
        };

        return moduleMap[pageType] || [];
    }
}

// Esporta il loader
window.FpExperiencesLoader = FpExperiencesLoader;
