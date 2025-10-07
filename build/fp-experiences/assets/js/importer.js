/**
 * FP Experiences Importer JavaScript
 * 
 * Handles CSV file validation and preview before import
 */

(function($) {
    'use strict';

    /**
     * CSV File validator and previewer
     */
    const ImporterValidator = {
        
        /**
         * Initialize the validator
         */
        init: function() {
            this.fileInput = $('#csv_file');
            this.submitBtn = $('button[name="fp_exp_import_experiences"]');
            this.previewContainer = $('#fp-exp-csv-preview');
            
            if (!this.fileInput.length) {
                return;
            }
            
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            this.fileInput.on('change', this.handleFileSelect.bind(this));
        },
        
        /**
         * Handle file selection
         */
        handleFileSelect: function(event) {
            const file = event.target.files[0];
            
            if (!file) {
                this.clearPreview();
                return;
            }
            
            // Validate file type
            if (!this.isValidFileType(file)) {
                this.showError('Formato file non valido. Per favore seleziona un file CSV.');
                this.submitBtn.prop('disabled', true);
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                this.showError('File troppo grande. Dimensione massima: 5MB.');
                this.submitBtn.prop('disabled', true);
                return;
            }
            
            // Read and preview file
            this.readAndPreview(file);
        },
        
        /**
         * Check if file type is valid
         */
        isValidFileType: function(file) {
            const validTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
            const validExtensions = ['.csv'];
            
            return validTypes.includes(file.type) || 
                   validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));
        },
        
        /**
         * Read file and show preview
         */
        readAndPreview: function(file) {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const csvData = this.parseCSV(e.target.result);
                    this.showPreview(csvData, file);
                    this.submitBtn.prop('disabled', false);
                } catch (error) {
                    this.showError('Errore nella lettura del file CSV: ' + error.message);
                    this.submitBtn.prop('disabled', true);
                }
            };
            
            reader.onerror = () => {
                this.showError('Errore nella lettura del file.');
                this.submitBtn.prop('disabled', true);
            };
            
            reader.readAsText(file);
        },
        
        /**
         * Parse CSV data
         */
        parseCSV: function(csvText) {
            const lines = csvText.split('\n').filter(line => line.trim());
            
            if (lines.length < 2) {
                throw new Error('Il file deve contenere almeno una riga di intestazione e una di dati.');
            }
            
            const headers = this.parseCSVLine(lines[0]);
            const rows = [];
            
            for (let i = 1; i < Math.min(lines.length, 6); i++) { // Preview max 5 rows
                const values = this.parseCSVLine(lines[i]);
                const row = {};
                
                headers.forEach((header, index) => {
                    row[header] = values[index] || '';
                });
                
                rows.push(row);
            }
            
            return {
                headers: headers,
                rows: rows,
                totalRows: lines.length - 1
            };
        },
        
        /**
         * Parse a single CSV line (handles quoted values)
         */
        parseCSVLine: function(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                
                if (char === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            
            result.push(current.trim());
            return result;
        },
        
        /**
         * Show CSV preview
         */
        showPreview: function(data, file) {
            if (!this.previewContainer.length) {
                this.previewContainer = $('<div id="fp-exp-csv-preview"></div>');
                this.fileInput.closest('td').append(this.previewContainer);
            }
            
            let html = '<div class="fp-exp-csv-preview-box" style="margin-top: 16px; padding: 16px; background: #f0f6fc; border: 1px solid #c8e1ff; border-radius: 8px;">';
            
            // File info
            html += '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">';
            html += '<span style="font-size: 24px;">📊</span>';
            html += '<div>';
            html += '<strong style="display: block; font-size: 14px;">' + this.escapeHtml(file.name) + '</strong>';
            html += '<span style="font-size: 12px; color: #57606a;">';
            html += data.totalRows + ' ' + (data.totalRows === 1 ? 'esperienza' : 'esperienze');
            html += ' • ' + this.formatFileSize(file.size);
            html += '</span>';
            html += '</div>';
            html += '</div>';
            
            // Check for required columns
            const hasTitle = data.headers.some(h => h.toLowerCase() === 'title');
            if (!hasTitle) {
                html += '<div style="padding: 8px 12px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 12px; border-radius: 4px;">';
                html += '<strong>⚠️ Attenzione:</strong> La colonna "title" sembra mancare. È obbligatoria per l\'import.';
                html += '</div>';
            }
            
            // Preview table
            html += '<div style="max-height: 300px; overflow: auto; background: white; border-radius: 6px; padding: 8px;">';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            
            // Headers
            html += '<thead><tr>';
            data.headers.forEach(header => {
                const isRequired = header.toLowerCase() === 'title';
                html += '<th style="text-align: left; padding: 8px; border-bottom: 2px solid #d0d7de; white-space: nowrap; ' + 
                        (isRequired ? 'font-weight: 700; color: #8b1e3f;' : '') + '">';
                html += this.escapeHtml(header);
                if (isRequired) html += ' *';
                html += '</th>';
            });
            html += '</tr></thead>';
            
            // Rows
            html += '<tbody>';
            data.rows.forEach((row, index) => {
                html += '<tr' + (index % 2 === 0 ? ' style="background: #f6f8fa;"' : '') + '>';
                data.headers.forEach(header => {
                    const value = row[header] || '';
                    const displayValue = value.length > 50 ? value.substring(0, 47) + '...' : value;
                    html += '<td style="padding: 6px 8px; border-bottom: 1px solid #d0d7de;">';
                    html += this.escapeHtml(displayValue);
                    html += '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody>';
            
            html += '</table>';
            html += '</div>';
            
            if (data.totalRows > 5) {
                html += '<p style="margin: 8px 0 0; font-size: 12px; color: #57606a;">';
                html += '... e altre ' + (data.totalRows - 5) + ' ' + (data.totalRows - 5 === 1 ? 'esperienza' : 'esperienze');
                html += '</p>';
            }
            
            html += '</div>';
            
            this.previewContainer.html(html);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            if (!this.previewContainer.length) {
                this.previewContainer = $('<div id="fp-exp-csv-preview"></div>');
                this.fileInput.closest('td').append(this.previewContainer);
            }
            
            const html = '<div class="notice notice-error" style="margin-top: 16px; padding: 12px;">' +
                        '<p style="margin: 0;"><strong>❌ Errore:</strong> ' + this.escapeHtml(message) + '</p>' +
                        '</div>';
            
            this.previewContainer.html(html);
        },
        
        /**
         * Clear preview
         */
        clearPreview: function() {
            if (this.previewContainer.length) {
                this.previewContainer.empty();
            }
            this.submitBtn.prop('disabled', false);
        },
        
        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        ImporterValidator.init();
    });
    
})(jQuery);
