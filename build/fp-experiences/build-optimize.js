#!/usr/bin/env node

/**
 * Build Script for FP Experiences Optimization
 * Script per l'ottimizzazione e la creazione dei file modulari
 */

const fs = require('fs');
const path = require('path');
const buildConfig = require('./build-config.js');

// Importa le dipendenze per l'ottimizzazione (se disponibili)
let terser, cleancss;
try {
    terser = require('terser');
    cleancss = require('clean-css');
} catch (e) {
    console.warn('⚠️  Dipendenze di ottimizzazione non disponibili. Build senza minificazione.');
}

class FpExperiencesBuilder {
    constructor() {
        this.config = buildConfig;
        this.outputDir = {
            js: this.config.build.output.js,
            css: this.config.build.output.css
        };
    }

    /**
     * Crea le directory di output se non esistono
     */
    createOutputDirectories() {
        Object.values(this.outputDir).forEach(dir => {
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
                console.log(`✓ Created directory: ${dir}`);
            }
        });
    }

    /**
     * Combina i file JavaScript
     */
    async combineJavaScript() {
        console.log('📦 Combining JavaScript files...');
        
        // Admin JS
        const adminFiles = Object.values(this.config.js.admin);
        const adminContent = this.combineFiles(adminFiles);
        const minifiedAdmin = await this.minifyJavaScript(adminContent);
        this.writeFile(`${this.outputDir.js}${this.config.build.outputFiles.adminJs}`, minifiedAdmin);
        
        // Frontend JS
        const frontendFiles = Object.values(this.config.js.frontend);
        const frontendContent = this.combineFiles(frontendFiles);
        const minifiedFrontend = await this.minifyJavaScript(frontendContent);
        this.writeFile(`${this.outputDir.js}${this.config.build.outputFiles.frontendJs}`, minifiedFrontend);
        
        // Combined JS
        const combinedContent = adminContent + '\n\n' + frontendContent;
        const minifiedCombined = await this.minifyJavaScript(combinedContent);
        this.writeFile(`${this.outputDir.js}${this.config.build.outputFiles.combinedJs}`, minifiedCombined);
        
        console.log('✓ JavaScript files combined and minified successfully');
    }

    /**
     * Combina i file CSS
     */
    combineCSS() {
        console.log('🎨 Combining CSS files...');
        
        // Admin CSS
        const adminFiles = Object.values(this.config.css.admin);
        const adminContent = this.combineFiles(adminFiles);
        const minifiedAdmin = this.minifyCSS(adminContent);
        this.writeFile(`${this.outputDir.css}${this.config.build.outputFiles.adminCss}`, minifiedAdmin);
        
        // Frontend CSS
        const frontendFiles = Object.values(this.config.css.frontend);
        const frontendContent = this.combineFiles(frontendFiles);
        const minifiedFrontend = this.minifyCSS(frontendContent);
        this.writeFile(`${this.outputDir.css}${this.config.build.outputFiles.frontendCss}`, minifiedFrontend);
        
        // Combined CSS
        const combinedContent = adminContent + '\n\n' + frontendContent;
        const minifiedCombined = this.minifyCSS(combinedContent);
        this.writeFile(`${this.outputDir.css}${this.config.build.outputFiles.combinedCss}`, minifiedCombined);
        
        console.log('✓ CSS files combined and minified successfully');
    }

    /**
     * Combina i file in un singolo contenuto
     */
    combineFiles(files) {
        let content = '';
        
        files.forEach(file => {
            if (fs.existsSync(file)) {
                const fileContent = fs.readFileSync(file, 'utf8');
                // Rimuovi gli @import relativi per CSS
                const processedContent = file.endsWith('.css') ? 
                    this.processCSSImports(fileContent, file) : fileContent;
                content += `/* ${file} */\n${processedContent}\n\n`;
            } else {
                console.warn(`⚠️  File not found: ${file}`);
            }
        });
        
        return content;
    }

    /**
     * Processa gli @import CSS per risolverli correttamente
     */
    processCSSImports(content, filePath) {
        const dir = path.dirname(filePath);
        
        // Sostituisci gli @import relativi con il contenuto effettivo
        // Gestisce sia @import "file.css" che @import url('./file.css')
        return content.replace(/@import\s+(?:url\()?['"]([^'"]+)['"](?:\))?;?/g, (match, importPath) => {
            const fullPath = path.resolve(dir, importPath);
            
            if (fs.existsSync(fullPath)) {
                const importContent = fs.readFileSync(fullPath, 'utf8');
                // Processa ricorsivamente gli import nel file importato
                const processedImportContent = this.processCSSImports(importContent, fullPath);
                return `/* Imported from ${importPath} */\n${processedImportContent}\n`;
            } else {
                console.warn(`⚠️  CSS import not found: ${fullPath}`);
                return `/* Missing import: ${importPath} */\n`;
            }
        });
    }

    /**
     * Scrive un file
     */
    writeFile(filePath, content) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`✓ Created: ${filePath}`);
    }

    /**
     * Minifica JavaScript se terser è disponibile
     */
    async minifyJavaScript(content) {
        if (!terser) {
            return content;
        }

        try {
            const result = await terser.minify(content, {
                compress: {
                    drop_console: true,
                    drop_debugger: true,
                    pure_funcs: ['console.log', 'console.info', 'console.debug']
                },
                mangle: {
                    toplevel: false
                },
                format: {
                    comments: false
                }
            });

            if (result.error) {
                console.warn('⚠️  Errore minificazione JS:', result.error);
                return content;
            }

            return result.code;
        } catch (error) {
            console.warn('⚠️  Errore minificazione JS:', error.message);
            return content;
        }
    }

    /**
     * Minifica CSS se clean-css è disponibile
     */
    minifyCSS(content) {
        if (!cleancss) {
            return content;
        }

        try {
            const result = new cleancss({
                level: 2,
                format: false,
                returnPromise: false
            }).minify(content);

            if (result.errors && result.errors.length > 0) {
                console.warn('⚠️  Errori minificazione CSS:', result.errors);
                return content;
            }

            return result.styles;
        } catch (error) {
            console.warn('⚠️  Errore minificazione CSS:', error.message);
            return content;
        }
    }

    /**
     * Crea un file di configurazione per il caricamento modulare
     */
    createModuleLoader() {
        const loaderContent = `
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
            script.src = \`/assets/js/dist/\${moduleName}.js\`;
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
`;

        this.writeFile('assets/js/dist/module-loader.js', loaderContent);
        console.log('✓ Module loader created');
    }

    /**
     * Crea un file di documentazione per l'uso modulare
     */
    createDocumentation() {
        const docContent = `# FP Experiences - Modular Architecture

## Struttura Modulare

### JavaScript Modules

#### Admin Modules
- \`core.js\` - Funzioni di base e utilità
- \`tabs.js\` - Gestione tab
- \`media-controls.js\` - Controlli media
- \`gallery-controls.js\` - Controlli galleria
- \`taxonomy-editors.js\` - Editor tassonomie
- \`repeaters.js\` - Componenti ripetibili
- \`form-validation.js\` - Validazione form
- \`calendar.js\` - Componenti calendario
- \`tools.js\` - Strumenti vari

#### Frontend Modules
- \`checkout.js\` - Processo checkout
- \`importer.js\` - Importazione dati

### CSS Modules

#### Admin CSS
- \`variables.css\` - Variabili CSS e stili base
- \`layout.css\` - Layout e struttura
- \`tabs.css\` - Componenti tab
- \`forms.css\` - Stili form
- \`media.css\` - Controlli media
- \`taxonomy.css\` - Editor tassonomie
- \`repeaters.css\` - Componenti ripetibili
- \`calendar.css\` - Calendario
- \`settings.css\` - Impostazioni
- \`buttons.css\` - Bottoni e azioni

#### Frontend CSS
- \`variables.css\` - Variabili CSS
- \`buttons.css\` - Bottoni
- \`cards.css\` - Card
- \`listing.css\` - Listing

## Utilizzo

### Caricamento Modulare
\`\`\`javascript
// Carica solo i moduli necessari
const loader = new FpExperiencesLoader();
await loader.loadPageModules('admin');
\`\`\`

### Caricamento Tradizionale
\`\`\`html
<!-- Carica tutto -->
<link rel="stylesheet" href="assets/css/dist/fp-experiences.min.css">
<script src="assets/js/dist/fp-experiences.min.js"></script>
\`\`\`

## Vantaggi

1. **Performance**: Caricamento solo dei moduli necessari
2. **Manutenibilità**: Codice organizzato in moduli logici
3. **Debugging**: Più facile identificare e correggere problemi
4. **Scalabilità**: Facile aggiungere nuovi moduli
5. **Cache**: Migliore gestione della cache del browser
`;

        this.writeFile('MODULAR-ARCHITECTURE.md', docContent);
        console.log('✓ Documentation created');
    }

    /**
     * Esegue il build completo
     */
    async build() {
        console.log('🚀 Starting FP Experiences optimization build...\n');
        
        try {
            this.createOutputDirectories();
            await this.combineJavaScript();
            this.combineCSS();
            this.createModuleLoader();
            this.createDocumentation();
            
            console.log('\n✅ Build completed successfully!');
            console.log('\n📁 Output files:');
            console.log(`   JS: ${this.outputDir.js}`);
            console.log(`   CSS: ${this.outputDir.css}`);
            console.log('\n📖 See MODULAR-ARCHITECTURE.md for usage instructions');
            
        } catch (error) {
            console.error('❌ Build failed:', error.message);
            process.exit(1);
        }
    }
}

// Esegui il build se chiamato direttamente
if (require.main === module) {
    const builder = new FpExperiencesBuilder();
    builder.build();
}

module.exports = FpExperiencesBuilder;
