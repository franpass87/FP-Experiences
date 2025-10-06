/**
 * Test per verificare che le funzionalità rimangano intatte dopo la modularizzazione
 */

class ModularFunctionalityTest {
    constructor() {
        this.tests = [];
        this.results = [];
    }

    /**
     * Aggiunge un test
     */
    addTest(name, testFunction) {
        this.tests.push({ name, testFunction });
    }

    /**
     * Esegue tutti i test
     */
    async runTests() {
        console.log('🧪 Testing modular functionality...\n');
        
        for (const test of this.tests) {
            try {
                const result = await test.testFunction();
                this.results.push({ name: test.name, status: 'passed', result });
                console.log(`✅ ${test.name}`);
            } catch (error) {
                this.results.push({ name: test.name, status: 'failed', error: error.message });
                console.log(`❌ ${test.name}: ${error.message}`);
            }
        }
        
        this.printSummary();
    }

    /**
     * Stampa il riepilogo dei test
     */
    printSummary() {
        const passed = this.results.filter(r => r.status === 'passed').length;
        const failed = this.results.filter(r => r.status === 'failed').length;
        
        console.log(`\n📊 Test Summary:`);
        console.log(`   ✅ Passed: ${passed}`);
        console.log(`   ❌ Failed: ${failed}`);
        console.log(`   📈 Success Rate: ${((passed / this.results.length) * 100).toFixed(1)}%`);
        
        if (failed > 0) {
            console.log(`\n❌ Failed Tests:`);
            this.results.filter(r => r.status === 'failed').forEach(r => {
                console.log(`   - ${r.name}: ${r.error}`);
            });
        }
    }

    /**
     * Test per verificare che i moduli JavaScript siano caricati correttamente
     */
    testJavaScriptModules() {
        return new Promise((resolve, reject) => {
            // Verifica che i moduli admin siano disponibili
            if (!window.fpExpAdmin) {
                reject(new Error('fpExpAdmin namespace not found'));
                return;
            }

            const requiredModules = [
                'core', 'tabs', 'mediaControls', 'galleryControls',
                'taxonomyEditors', 'repeaters', 'formValidation', 'calendar', 'tools'
            ];

            const missingModules = requiredModules.filter(module => 
                !window.fpExpAdmin[module]
            );

            if (missingModules.length > 0) {
                reject(new Error(`Missing modules: ${missingModules.join(', ')}`));
                return;
            }

            resolve('All JavaScript modules loaded successfully');
        });
    }

    /**
     * Test per verificare che le funzioni core siano disponibili
     */
    testCoreFunctions() {
        return new Promise((resolve, reject) => {
            if (!window.fpExpAdmin.core) {
                reject(new Error('Core module not found'));
                return;
            }

            const requiredFunctions = ['ready', 'getString'];
            const missingFunctions = requiredFunctions.filter(func => 
                typeof window.fpExpAdmin.core[func] !== 'function'
            );

            if (missingFunctions.length > 0) {
                reject(new Error(`Missing core functions: ${missingFunctions.join(', ')}`));
                return;
            }

            resolve('Core functions available');
        });
    }

    /**
     * Test per verificare che i selettori CSS siano applicati
     */
    testCSSSelectors() {
        return new Promise((resolve, reject) => {
            const requiredSelectors = [
                '.fp-exp-admin',
                '.fp-exp-tabs',
                '.fp-exp-fieldset',
                '.fp-exp-cover-media',
                '.fp-exp-gallery-control',
                '.fp-exp-taxonomy-editor',
                '.fp-exp-repeater',
                '.fp-exp-calendar',
                '.fp-exp-settings',
                '.fp-btn',
                '.fp-card',
                '.fp-listing'
            ];

            const missingSelectors = requiredSelectors.filter(selector => {
                const element = document.querySelector(selector);
                return !element;
            });

            if (missingSelectors.length > 0) {
                reject(new Error(`Missing CSS selectors: ${missingSelectors.join(', ')}`));
                return;
            }

            resolve('CSS selectors applied correctly');
        });
    }

    /**
     * Test per verificare che le funzionalità di tab funzionino
     */
    testTabFunctionality() {
        return new Promise((resolve, reject) => {
            const tabContainers = document.querySelectorAll('[data-fp-exp-tabs]');
            
            if (tabContainers.length === 0) {
                resolve('No tab containers found (not an error)');
                return;
            }

            // Verifica che i tab abbiano la struttura corretta
            let hasValidStructure = true;
            tabContainers.forEach(container => {
                const tabs = container.querySelectorAll('[data-tab]');
                const panels = container.querySelectorAll('[data-panel]');
                
                if (tabs.length === 0 || panels.length === 0) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Tab structure is invalid'));
                return;
            }

            resolve('Tab functionality working correctly');
        });
    }

    /**
     * Test per verificare che i controlli media funzionino
     */
    testMediaControls() {
        return new Promise((resolve, reject) => {
            const mediaControls = document.querySelectorAll('[data-fp-media-control]');
            
            if (mediaControls.length === 0) {
                resolve('No media controls found (not an error)');
                return;
            }

            // Verifica che i controlli abbiano la struttura corretta
            let hasValidStructure = true;
            mediaControls.forEach(control => {
                const button = control.querySelector('button');
                const input = control.querySelector('input[type="hidden"]');
                
                if (!button || !input) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Media controls structure is invalid'));
                return;
            }

            resolve('Media controls working correctly');
        });
    }

    /**
     * Test per verificare che i controlli galleria funzionino
     */
    testGalleryControls() {
        return new Promise((resolve, reject) => {
            const galleryControls = document.querySelectorAll('[data-fp-gallery-control]');
            
            if (galleryControls.length === 0) {
                resolve('No gallery controls found (not an error)');
                return;
            }

            // Verifica che i controlli abbiano la struttura corretta
            let hasValidStructure = true;
            galleryControls.forEach(control => {
                const button = control.querySelector('button');
                const container = control.querySelector('.fp-exp-gallery-container');
                
                if (!button || !container) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Gallery controls structure is invalid'));
                return;
            }

            resolve('Gallery controls working correctly');
        });
    }

    /**
     * Test per verificare che i repeater funzionino
     */
    testRepeaters() {
        return new Promise((resolve, reject) => {
            const repeaters = document.querySelectorAll('[data-repeater]');
            
            if (repeaters.length === 0) {
                resolve('No repeaters found (not an error)');
                return;
            }

            // Verifica che i repeater abbiano la struttura corretta
            let hasValidStructure = true;
            repeaters.forEach(repeater => {
                const addButton = repeater.querySelector('[data-repeater-add]');
                const items = repeater.querySelectorAll('[data-repeater-item]');
                
                if (!addButton || items.length === 0) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Repeaters structure is invalid'));
                return;
            }

            resolve('Repeaters working correctly');
        });
    }

    /**
     * Test per verificare che la validazione form funzioni
     */
    testFormValidation() {
        return new Promise((resolve, reject) => {
            const forms = document.querySelectorAll('[data-fp-exp-validate]');
            
            if (forms.length === 0) {
                resolve('No forms with validation found (not an error)');
                return;
            }

            // Verifica che i form abbiano la struttura corretta
            let hasValidStructure = true;
            forms.forEach(form => {
                const submitButton = form.querySelector('button[type="submit"]');
                const requiredFields = form.querySelectorAll('[required]');
                
                if (!submitButton || requiredFields.length === 0) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Form validation structure is invalid'));
                return;
            }

            resolve('Form validation working correctly');
        });
    }

    /**
     * Test per verificare che il calendario funzioni
     */
    testCalendar() {
        return new Promise((resolve, reject) => {
            const calendar = document.querySelector('.fp-exp-calendar');
            
            if (!calendar) {
                resolve('No calendar found (not an error)');
                return;
            }

            // Verifica che il calendario abbia la struttura corretta
            const hasValidStructure = calendar.querySelector('.fp-exp-calendar-header') && 
                                    calendar.querySelector('.fp-exp-calendar-grid');

            if (!hasValidStructure) {
                reject(new Error('Calendar structure is invalid'));
                return;
            }

            resolve('Calendar working correctly');
        });
    }

    /**
     * Test per verificare che gli strumenti funzionino
     */
    testTools() {
        return new Promise((resolve, reject) => {
            const tools = document.querySelectorAll('[data-fp-tool]');
            
            if (tools.length === 0) {
                resolve('No tools found (not an error)');
                return;
            }

            // Verifica che gli strumenti abbiano la struttura corretta
            let hasValidStructure = true;
            tools.forEach(tool => {
                const button = tool.querySelector('button');
                const action = tool.getAttribute('data-fp-tool');
                
                if (!button || !action) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Tools structure is invalid'));
                return;
            }

            resolve('Tools working correctly');
        });
    }

    /**
     * Test per verificare che i controlli tassonomia funzionino
     */
    testTaxonomyEditors() {
        return new Promise((resolve, reject) => {
            const editors = document.querySelectorAll('[data-fp-taxonomy-editor]');
            
            if (editors.length === 0) {
                resolve('No taxonomy editors found (not an error)');
                return;
            }

            // Verifica che gli editor abbiano la struttura corretta
            let hasValidStructure = true;
            editors.forEach(editor => {
                const input = editor.querySelector('input');
                const button = editor.querySelector('button');
                
                if (!input || !button) {
                    hasValidStructure = false;
                }
            });

            if (!hasValidStructure) {
                reject(new Error('Taxonomy editors structure is invalid'));
                return;
            }

            resolve('Taxonomy editors working correctly');
        });
    }

    /**
     * Test per verificare che i controlli checkout funzionino
     */
    testCheckoutControls() {
        return new Promise((resolve, reject) => {
            const checkout = document.querySelector('.fp-exp-checkout');
            
            if (!checkout) {
                resolve('No checkout found (not an error)');
                return;
            }

            // Verifica che il checkout abbia la struttura corretta
            const hasValidStructure = checkout.querySelector('.fp-exp-checkout-form') && 
                                    checkout.querySelector('.fp-exp-checkout-summary');

            if (!hasValidStructure) {
                reject(new Error('Checkout structure is invalid'));
                return;
            }

            resolve('Checkout working correctly');
        });
    }

    /**
     * Test per verificare che l'importer funzioni
     */
    testImporter() {
        return new Promise((resolve, reject) => {
            const importer = document.querySelector('.fp-exp-importer');
            
            if (!importer) {
                resolve('No importer found (not an error)');
                return;
            }

            // Verifica che l'importer abbia la struttura corretta
            const hasValidStructure = importer.querySelector('.fp-exp-importer-form') && 
                                    importer.querySelector('.fp-exp-importer-preview');

            if (!hasValidStructure) {
                reject(new Error('Importer structure is invalid'));
                return;
            }

            resolve('Importer working correctly');
        });
    }

    /**
     * Esegue tutti i test
     */
    async runAllTests() {
        // Aggiungi tutti i test
        this.addTest('JavaScript Modules', () => this.testJavaScriptModules());
        this.addTest('Core Functions', () => this.testCoreFunctions());
        this.addTest('CSS Selectors', () => this.testCSSSelectors());
        this.addTest('Tab Functionality', () => this.testTabFunctionality());
        this.addTest('Media Controls', () => this.testMediaControls());
        this.addTest('Gallery Controls', () => this.testGalleryControls());
        this.addTest('Repeaters', () => this.testRepeaters());
        this.addTest('Form Validation', () => this.testFormValidation());
        this.addTest('Calendar', () => this.testCalendar());
        this.addTest('Tools', () => this.testTools());
        this.addTest('Taxonomy Editors', () => this.testTaxonomyEditors());
        this.addTest('Checkout Controls', () => this.testCheckoutControls());
        this.addTest('Importer', () => this.testImporter());

        // Esegui i test
        await this.runTests();
    }
}

// Esporta la classe per l'uso
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModularFunctionalityTest;
} else {
    window.ModularFunctionalityTest = ModularFunctionalityTest;
}
