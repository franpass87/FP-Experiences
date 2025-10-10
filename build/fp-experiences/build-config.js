/**
 * Build Configuration for FP Experiences
 * Configurazione per il sistema di build modulare
 */

const buildConfig = {
    // Configurazione JavaScript
    js: {
        // Moduli admin
        admin: {
            core: 'assets/js/admin/core.js',
            tabs: 'assets/js/admin/tabs.js',
            mediaControls: 'assets/js/admin/media-controls.js',
            galleryControls: 'assets/js/admin/gallery-controls.js',
            taxonomyEditors: 'assets/js/admin/taxonomy-editors.js',
            repeaters: 'assets/js/admin/repeaters.js',
            formValidation: 'assets/js/admin/form-validation.js',
            calendar: 'assets/js/admin/calendar.js',
            tools: 'assets/js/admin/tools.js',
            main: 'assets/js/admin/main.js'
        },
        // Moduli frontend
        frontend: {
            frontAvailability: 'assets/js/front/availability.js',
            frontSlots: 'assets/js/front/slots.js',
            frontSummaryRtb: 'assets/js/front/summary-rtb.js',
            frontSummaryWoo: 'assets/js/front/summary-woo.js',
            frontCalendar: 'assets/js/front/calendar.js',
            frontQuantity: 'assets/js/front/quantity.js',
            front: 'assets/js/front.js',
            checkout: 'assets/js/checkout.js',
            importer: 'assets/js/importer.js'
        }
    },
    
    // Configurazione CSS
    css: {
        // Moduli admin - solo i file principali che importano gli altri
        admin: {
            main: 'assets/css/admin/main.css'
        },
        // Moduli frontend - solo i file principali che importano gli altri
        frontend: {
            main: 'assets/css/front/main.css'
        }
    },
    
    // Configurazione build
    build: {
        // Output directories
        output: {
            js: 'assets/js/dist/',
            css: 'assets/css/dist/'
        },
        
        // File di output
        outputFiles: {
            // Admin
            adminJs: 'fp-experiences-admin.min.js',
            adminCss: 'fp-experiences-admin.min.css',
            
            // Frontend
            frontendJs: 'fp-experiences-frontend.min.js',
            frontendCss: 'fp-experiences-frontend.min.css',
            
            // Combined
            combinedJs: 'fp-experiences.min.js',
            combinedCss: 'fp-experiences.min.css'
        }
    }
};

module.exports = buildConfig;
