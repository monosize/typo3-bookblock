// BookBlock PDF Converter Service
// Modern JavaScript service for PDF-to-image conversion

export class PdfConverter {
  constructor(options = {}) {
    this.defaultOptions = {
      dpi: 150,
      format: 'jpg',
      quality: 90,
      scale: 1.0,
      ...options
    }
    
    this.logger = console
  }

  /**
   * Convert PDF to images via TYPO3 backend service
   * @param {string} pdfFile - PDF file path or URL
   * @param {object} options - Conversion options
   * @returns {Promise<Array>} Array of image objects
   */
  async convertToImages(pdfFile, options = {}) {
    const config = { ...this.defaultOptions, ...options }
    
    try {
      this.logger.debug('Converting PDF to images', { pdfFile, config })
      
      // Call TYPO3 backend service via AJAX
      const response = await this.callBackendService('convertPdfToImages', {
        pdfFile,
        config
      })
      
      if (!response.success) {
        throw new Error(response.error || 'PDF conversion failed')
      }
      
      return response.images || []
      
    } catch (error) {
      this.logger.error('PDF conversion failed', error)
      throw error
    }
  }

  /**
   * Get PDF page count
   * @param {string} pdfFile - PDF file path or URL
   * @returns {Promise<number>} Number of pages
   */
  async getPageCount(pdfFile) {
    try {
      const response = await this.callBackendService('getPdfPageCount', {
        pdfFile
      })
      
      return response.pageCount || 0
      
    } catch (error) {
      this.logger.error('Failed to get PDF page count', error)
      return 0
    }
  }

  /**
   * Call TYPO3 backend service
   * @param {string} action - Service action
   * @param {object} data - Request data
   * @returns {Promise<object>} Response data
   */
  async callBackendService(action, data) {
    const formData = new FormData()
    formData.append('tx_bookblock_bookblock[action]', action)
    formData.append('tx_bookblock_bookblock[data]', JSON.stringify(data))
    
    // Add TYPO3 request token if available
    if (window.TYPO3 && window.TYPO3.settings && window.TYPO3.settings.ajaxToken) {
      formData.append('ajaxToken', window.TYPO3.settings.ajaxToken)
    }
    
    const response = await fetch('/?eID=bookblock_pdf_converter', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const result = await response.json()
    return result
  }

  /**
   * Validate PDF file
   * @param {string} pdfFile - PDF file path or URL
   * @returns {Promise<boolean>} True if valid PDF
   */
  async validatePdf(pdfFile) {
    try {
      const response = await this.callBackendService('validatePdf', {
        pdfFile
      })
      
      return response.valid === true
      
    } catch (error) {
      this.logger.error('PDF validation failed', error)
      return false
    }
  }
}

// Factory function for easy instantiation
export function createPdfConverter(options = {}) {
  return new PdfConverter(options)
}

// Default export
export default PdfConverter