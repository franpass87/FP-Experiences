
/**
 * FP Experiences Module Loader
 * Caricatore modulare per ottimizzare il caricamento
 */

const FP_EXP_LOADER_SCRIPT = (typeof document !== 'undefined' ? (document.currentScript || (function () {
    const scripts = document.getElementsByTagName('script');
    for (let i = scripts.length - 1; i >= 0; i--) {
        const candidate = scripts[i];
        if (candidate && candidate.src && candidate.src.indexOf('module-loader.js') !== -1) {
            return candidate;
        }
    }
    return null;
})()) : null);

function fpExpNormaliseBaseUrl(url) {
    if (typeof url !== 'string' || url === '') {
        return '';
    }

    return url.endsWith('/') ? url : url + '/';
}

function fpExpDetectLoaderBaseUrl() {
    if (typeof window !== 'undefined' && typeof window.fpExpModuleBaseUrl === 'string' && window.fpExpModuleBaseUrl) {
        return fpExpNormaliseBaseUrl(window.fpExpModuleBaseUrl);
    }

    if (FP_EXP_LOADER_SCRIPT && FP_EXP_LOADER_SCRIPT.src) {
        const withoutFile = FP_EXP_LOADER_SCRIPT.src.replace(/module-loader\.js(?:\?.*)?$/, '');
        return fpExpNormaliseBaseUrl(withoutFile);
    }

    if (typeof window !== 'undefined' && window.fpExpConfig && typeof window.fpExpConfig.pluginUrl === 'string') {
        return fpExpNormaliseBaseUrl(window.fpExpConfig.pluginUrl + 'assets/js/dist/');
    }

    return '';
}

class FpExperiencesLoader {
    constructor(options = {}) {
        this.loadedModules = new Set();
        this.moduleCallbacks = new Map();
        this.baseUrl = fpExpNormaliseBaseUrl(options.baseUrl || fpExpDetectLoaderBaseUrl());
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
            const base = this.baseUrl || fpExpDetectLoaderBaseUrl();
            const src = base ? base + moduleName + '.js' : moduleName + '.js';
            script.src = src;
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
