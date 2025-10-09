// BookBlock Stimulus Controller
// Modern JavaScript implementation replacing jQuery dependencies

import { Controller } from "@hotwired/stimulus"
import { PdfConverter } from "../services/PdfConverter.js"

export default class extends Controller {
  static targets = [
    "container",
    "allItems", 
    "page",
    "item",
    "overlay",
    "flipOverlay",
    "navigation",
    "toolbar",
    "closeButton"
  ]
  
  static values = {
    orientation: { type: String, default: "vertical" },
    animationSpeed: { type: Number, default: 600 },
    autoPlay: { type: Boolean, default: false },
    autoPlayInterval: { type: Number, default: 5000 },
    loop: { type: Boolean, default: false },
    enableZoom: { type: Boolean, default: true },
    showThumbnails: { type: Boolean, default: false },
    responsive: { type: Boolean, default: true },
    mobileBreakpoint: { type: Number, default: 768 },
    pdfFile: String,
    bookBlockId: String
  }
  
  static classes = [
    "flipping",
    "flipNext", 
    "flipPrev",
    "flipInitial",
    "flipNextEnd",
    "flipPrevEnd",
    "active",
    "visible"
  ]

  // Lifecycle Methods
  connect() {
    this.logger = console
    this.currentPage = 0
    this.totalPages = 0
    this.isAnimating = false
    this.autoPlayTimer = null
    this.touchStartX = null
    this.touchStartY = null
    this.isTouch = 'ontouchstart' in window
    
    this.logger.debug("BookBlock controller connected", { 
      element: this.element,
      orientation: this.orientationValue,
      enableZoom: this.enableZoomValue
    })
    
    this.initialize()
  }
  
  disconnect() {
    this.cleanup()
    this.logger.debug("BookBlock controller disconnected")
  }
  
  // Initialization
  async initialize() {
    try {
      // Setup container and pages
      await this.setupContainer()
      await this.setupPages()
      
      // Setup navigation
      this.setupNavigation()
      this.setupKeyboardNavigation()
      
      // Setup touch/gesture support
      if (this.isTouch) {
        this.setupTouchNavigation()
      }
      
      // Setup responsive behavior
      if (this.responsiveValue) {
        this.setupResponsive()
      }
      
      // Load PDF if specified
      if (this.pdfFileValue) {
        await this.loadPdf()
      }
      
      // Setup auto-play
      if (this.autoPlayValue) {
        this.startAutoPlay()
      }
      
      // Initialize first page
      this.showPage(0)
      
      // Dispatch ready event
      this.dispatch("ready", { 
        detail: { 
          totalPages: this.totalPages, 
          currentPage: this.currentPage 
        }
      })
      
    } catch (error) {
      this.logger.error("BookBlock initialization failed", error)
      this.dispatch("error", { detail: { error } })
    }
  }
  
  async setupContainer() {
    // Add CSS classes for functionality
    this.element.classList.add("bb-bookblock")
    this.element.setAttribute("data-orientation", this.orientationValue)
    
    // Setup container properties
    if (this.hasContainerTarget) {
      this.containerTarget.classList.add("bb-container")
    }
    
    if (this.hasAllItemsTarget) {
      this.allItemsTarget.classList.add("bb-all-items", `bb-${this.orientationValue}`)
    }
  }
  
  async setupPages() {
    this.pageTargets.forEach((page, index) => {
      page.classList.add("bb-page")
      page.setAttribute("data-page", index)
      page.style.zIndex = this.pageTargets.length - index
      
      // Setup page content
      const items = page.querySelectorAll(".bb-item")
      items.forEach(item => {
        item.classList.add("bb-item")
        if (index > 0) {
          item.style.display = "none"
        }
      })
    })
    
    this.totalPages = this.pageTargets.length
    this.logger.debug(`Setup ${this.totalPages} pages`)
  }
  
  setupNavigation() {
    // Navigation click handlers are handled by Stimulus actions
    // Setup navigation state
    this.updateNavigationState()
  }
  
  setupKeyboardNavigation() {
    // Focus management
    this.element.setAttribute("tabindex", "0")
    
    // Keyboard event listener
    this.keyboardHandler = this.handleKeyboard.bind(this)
    document.addEventListener("keydown", this.keyboardHandler)
  }
  
  setupTouchNavigation() {
    this.element.addEventListener("touchstart", this.handleTouchStart.bind(this), { passive: true })
    this.element.addEventListener("touchend", this.handleTouchEnd.bind(this), { passive: true })
    this.element.addEventListener("touchmove", this.handleTouchMove.bind(this), { passive: false })
  }
  
  setupResponsive() {
    this.resizeHandler = this.handleResize.bind(this)
    window.addEventListener("resize", this.resizeHandler)
    this.handleResize() // Initial check
  }
  
  // PDF Loading
  async loadPdf() {
    try {
      if (!this.pdfFileValue) return
      
      this.dispatch("pdfLoading", { detail: { file: this.pdfFileValue } })
      
      const pdfConverter = new PdfConverter()
      const images = await pdfConverter.convertToImages(this.pdfFileValue, {
        dpi: 150,
        format: 'jpg',
        quality: 90
      })
      
      if (images && images.length > 0) {
        await this.createPagesFromImages(images)
        this.dispatch("pdfLoaded", { detail: { images, totalPages: images.length } })
      }
      
    } catch (error) {
      this.logger.error("PDF loading failed", error)
      this.dispatch("pdfError", { detail: { error } })
    }
  }
  
  async createPagesFromImages(images) {
    // Clear existing pages
    this.allItemsTarget.innerHTML = ""
    
    images.forEach((image, index) => {
      const page = this.createPageElement(image, index)
      this.allItemsTarget.appendChild(page)
    })
    
    // Re-setup pages
    await this.setupPages()
  }
  
  createPageElement(image, index) {
    const page = document.createElement("div")
    page.className = "bb-page"
    page.setAttribute("data-bookblock-target", "page")
    
    const outer = document.createElement("div")
    outer.className = "bb-outer"
    
    const content = document.createElement("div") 
    content.className = "bb-content"
    
    const inner = document.createElement("div")
    inner.className = "bb-inner"
    
    const item = document.createElement("div")
    item.className = "bb-item"
    item.setAttribute("data-bookblock-target", "item")
    
    const img = document.createElement("img")
    img.src = image.url
    img.alt = `Page ${index + 1}`
    img.loading = "lazy"
    img.decoding = "async"
    
    item.appendChild(img)
    inner.appendChild(item)
    content.appendChild(inner)
    outer.appendChild(content)
    page.appendChild(outer)
    
    return page
  }
  
  // Navigation Actions (Stimulus Actions)
  nextPage(event) {
    event?.preventDefault()
    if (this.currentPage < this.totalPages - 1) {
      this.goToPage(this.currentPage + 1)
    } else if (this.loopValue) {
      this.goToPage(0)
    }
  }
  
  prevPage(event) {
    event?.preventDefault()
    if (this.currentPage > 0) {
      this.goToPage(this.currentPage - 1)
    } else if (this.loopValue) {
      this.goToPage(this.totalPages - 1)
    }
  }
  
  firstPage(event) {
    event?.preventDefault()
    this.goToPage(0)
  }
  
  lastPage(event) {
    event?.preventDefault()
    this.goToPage(this.totalPages - 1)
  }
  
  goToPage(pageIndex) {
    if (this.isAnimating || pageIndex === this.currentPage || pageIndex < 0 || pageIndex >= this.totalPages) {
      return
    }
    
    this.isAnimating = true
    const direction = pageIndex > this.currentPage ? "next" : "prev"
    
    this.dispatch("pageChanging", { 
      detail: { 
        from: this.currentPage, 
        to: pageIndex, 
        direction 
      }
    })
    
    this.animateToPage(pageIndex, direction)
  }
  
  async animateToPage(pageIndex, direction) {
    const currentPageEl = this.pageTargets[this.currentPage]
    const targetPageEl = this.pageTargets[pageIndex]
    
    if (!currentPageEl || !targetPageEl) return
    
    // Show flip overlay
    if (this.hasFlipOverlayTarget) {
      this.flipOverlayTarget.classList.add(this.activeClass)
    }
    
    // Apply animation classes
    if (direction === "next") {
      targetPageEl.classList.add(this.flipNextClass, this.flipInitialClass)
      currentPageEl.classList.add(this.flipNextClass)
    } else {
      targetPageEl.classList.add(this.flipPrevClass, this.flipInitialClass)
      currentPageEl.classList.add(this.flipPrevClass)
    }
    
    // Wait for animation
    await this.wait(this.animationSpeedValue / 2)
    
    // Apply end state classes
    if (direction === "next") {
      targetPageEl.classList.add(this.flipNextEndClass)
      currentPageEl.classList.add(this.flipNextEndClass)
    } else {
      targetPageEl.classList.add(this.flipPrevEndClass)
      currentPageEl.classList.add(this.flipPrevEndClass)
    }
    
    // Wait for animation completion
    await this.wait(this.animationSpeedValue / 2)
    
    // Clean up and finalize
    this.finalizePage(pageIndex, currentPageEl, targetPageEl)
  }
  
  finalizePage(pageIndex, currentPageEl, targetPageEl) {
    // Remove animation classes
    const animationClasses = [
      this.flipNextClass, 
      this.flipPrevClass, 
      this.flipInitialClass, 
      this.flipNextEndClass, 
      this.flipPrevEndClass
    ]
    
    this.pageTargets.forEach(page => {
      animationClasses.forEach(cls => page.classList.remove(cls))
    })
    
    // Hide flip overlay
    if (this.hasFlipOverlayTarget) {
      this.flipOverlayTarget.classList.remove(this.activeClass)
    }
    
    // Update current page
    this.currentPage = pageIndex
    this.isAnimating = false
    
    // Update navigation state
    this.updateNavigationState()
    
    // Dispatch event
    this.dispatch("pageChanged", { 
      detail: { 
        currentPage: this.currentPage, 
        totalPages: this.totalPages 
      }
    })
    
    this.logger.debug(`Navigated to page ${this.currentPage + 1}`)
  }
  
  showPage(pageIndex) {
    if (pageIndex < 0 || pageIndex >= this.totalPages) return
    
    this.pageTargets.forEach((page, index) => {
      const items = page.querySelectorAll(".bb-item")
      items.forEach(item => {
        item.style.display = index === pageIndex ? "block" : "none"
      })
    })
    
    this.currentPage = pageIndex
    this.updateNavigationState()
  }
  
  updateNavigationState() {
    // Update navigation buttons state
    const prevButtons = this.element.querySelectorAll("[data-action*='prevPage']")
    const nextButtons = this.element.querySelectorAll("[data-action*='nextPage']")
    
    prevButtons.forEach(btn => {
      btn.disabled = this.currentPage === 0 && !this.loopValue
      btn.setAttribute("aria-disabled", btn.disabled)
    })
    
    nextButtons.forEach(btn => {
      btn.disabled = this.currentPage === this.totalPages - 1 && !this.loopValue
      btn.setAttribute("aria-disabled", btn.disabled)
    })
    
    // Update ARIA attributes
    this.element.setAttribute("aria-valuenow", this.currentPage + 1)
    this.element.setAttribute("aria-valuemax", this.totalPages)
  }
  
  // Event Handlers
  handleKeyboard(event) {
    if (!this.element.contains(document.activeElement)) return
    
    switch (event.key) {
      case "ArrowLeft":
        event.preventDefault()
        this.orientationValue === "vertical" ? this.prevPage() : this.prevPage()
        break
      case "ArrowRight":
        event.preventDefault()  
        this.orientationValue === "vertical" ? this.nextPage() : this.nextPage()
        break
      case "ArrowUp":
        event.preventDefault()
        this.orientationValue === "horizontal" ? this.prevPage() : this.prevPage()
        break
      case "ArrowDown":
        event.preventDefault()
        this.orientationValue === "horizontal" ? this.nextPage() : this.nextPage()
        break
      case "Home":
        event.preventDefault()
        this.firstPage()
        break
      case "End":
        event.preventDefault()
        this.lastPage()
        break
      case "Escape":
        event.preventDefault()
        this.close()
        break
    }
  }
  
  handleTouchStart(event) {
    if (event.touches.length !== 1) return
    
    const touch = event.touches[0]
    this.touchStartX = touch.clientX
    this.touchStartY = touch.clientY
  }
  
  handleTouchMove(event) {
    if (!this.touchStartX || !this.touchStartY) return
    
    const touch = event.touches[0]
    const deltaX = touch.clientX - this.touchStartX
    const deltaY = touch.clientY - this.touchStartY
    
    // Prevent scrolling during swipe
    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      event.preventDefault()
    }
  }
  
  handleTouchEnd(event) {
    if (!this.touchStartX || !this.touchStartY) return
    
    const touch = event.changedTouches[0]
    const deltaX = touch.clientX - this.touchStartX
    const deltaY = touch.clientY - this.touchStartY
    
    const minSwipeDistance = 50
    
    if (this.orientationValue === "vertical") {
      if (Math.abs(deltaX) > minSwipeDistance && Math.abs(deltaX) > Math.abs(deltaY)) {
        if (deltaX > 0) {
          this.prevPage()
        } else {
          this.nextPage()
        }
      }
    } else {
      if (Math.abs(deltaY) > minSwipeDistance && Math.abs(deltaY) > Math.abs(deltaX)) {
        if (deltaY > 0) {
          this.prevPage()
        } else {
          this.nextPage()
        }
      }
    }
    
    this.touchStartX = null
    this.touchStartY = null
  }
  
  handleResize() {
    const isMobile = window.innerWidth <= this.mobileBreakpointValue
    this.element.classList.toggle("mobile", isMobile)
    
    this.dispatch("resize", { detail: { isMobile, width: window.innerWidth } })
  }
  
  // Auto-play functionality  
  startAutoPlay() {
    if (this.autoPlayTimer) return
    
    this.autoPlayTimer = setInterval(() => {
      if (!this.isAnimating) {
        this.nextPage()
      }
    }, this.autoPlayIntervalValue)
    
    this.logger.debug("Auto-play started")
  }
  
  stopAutoPlay() {
    if (this.autoPlayTimer) {
      clearInterval(this.autoPlayTimer)
      this.autoPlayTimer = null
      this.logger.debug("Auto-play stopped")
    }
  }
  
  toggleAutoPlay() {
    if (this.autoPlayTimer) {
      this.stopAutoPlay()
    } else {
      this.startAutoPlay()
    }
  }
  
  // Modal functionality
  open() {
    this.element.classList.add("active")
    document.body.classList.add("bookblock-open")
    this.element.focus()
    
    this.dispatch("opened")
  }
  
  close() {
    this.element.classList.remove("active")
    document.body.classList.remove("bookblock-open")
    
    this.stopAutoPlay()
    this.dispatch("closed")
  }
  
  toggle() {
    if (this.element.classList.contains("active")) {
      this.close()
    } else {
      this.open()
    }
  }
  
  // Utility methods
  async wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms))
  }
  
  cleanup() {
    // Remove event listeners
    if (this.keyboardHandler) {
      document.removeEventListener("keydown", this.keyboardHandler)
    }
    
    if (this.resizeHandler) {
      window.removeEventListener("resize", this.resizeHandler)
    }
    
    // Stop auto-play
    this.stopAutoPlay()
    
    // Clean up any other resources
    document.body.classList.remove("bookblock-open")
  }
  
  // Public API methods
  getCurrentPage() {
    return this.currentPage
  }
  
  getTotalPages() {
    return this.totalPages
  }
  
  getOrientation() {
    return this.orientationValue
  }
  
  setOrientation(orientation) {
    this.orientationValue = orientation
    this.element.setAttribute("data-orientation", orientation)
    
    if (this.hasAllItemsTarget) {
      this.allItemsTarget.className = this.allItemsTarget.className.replace(/bb-(vertical|horizontal)/, `bb-${orientation}`)
    }
    
    this.dispatch("orientationChanged", { detail: { orientation } })
  }
}