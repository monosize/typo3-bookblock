// BookBlock Thumbnail Stimulus Controller
// Modern JavaScript implementation for thumbnail navigation

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = [
    "thumbnails",
    "grid", 
    "thumbnail",
    "toggle",
    "navigation",
    "prevButton",
    "nextButton",
    "counter"
  ]
  
  static values = {
    visible: { type: Boolean, default: false },
    autoGenerate: { type: Boolean, default: true },
    maxVisible: { type: Number, default: 10 },
    scrollStep: { type: Number, default: 3 },
    animationDuration: { type: Number, default: 300 },
    lazyLoad: { type: Boolean, default: true }
  }
  
  static classes = [
    "visible",
    "active",
    "loading"
  ]

  // Lifecycle
  connect() {
    this.logger = console
    this.currentIndex = 0
    this.scrollPosition = 0
    this.thumbnailElements = []
    this.isTouch = 'ontouchstart' in window
    
    this.logger.debug("BookBlock Thumbnail controller connected")
    
    this.initialize()
  }
  
  disconnect() {
    this.cleanup()
    this.logger.debug("BookBlock Thumbnail controller disconnected")
  }
  
  // Initialization
  async initialize() {
    try {
      await this.setupThumbnails()
      this.setupNavigation()
      this.setupKeyboard()
      
      if (this.isTouch) {
        this.setupTouch()
      }
      
      this.updateThumbnailsState()
      
      // Listen for BookBlock page changes
      this.element.addEventListener("bookblock:pageChanged", this.handlePageChanged.bind(this))
      
      this.dispatch("ready", { detail: { thumbnailCount: this.thumbnailElements.length } })
      
    } catch (error) {
      this.logger.error("Thumbnail initialization failed", error)
      this.dispatch("error", { detail: { error } })
    }
  }
  
  async setupThumbnails() {
    if (this.autoGenerateValue) {
      await this.generateThumbnails()
    } else {
      this.collectExistingThumbnails()
    }
    
    this.setupThumbnailEvents()
  }
  
  async generateThumbnails() {
    // Get BookBlock pages from parent controller
    const bookBlockElement = this.element.closest("[data-controller*='bookblock']")
    if (!bookBlockElement) {
      this.logger.warn("No BookBlock parent found for thumbnail generation")
      return
    }
    
    const pages = bookBlockElement.querySelectorAll("[data-bookblock-target='page']")
    
    if (!this.hasGridTarget) {
      this.logger.warn("No thumbnail grid target found")
      return
    }
    
    // Clear existing thumbnails
    this.gridTarget.innerHTML = ""
    this.thumbnailElements = []
    
    // Generate thumbnails for each page
    for (let i = 0; i < pages.length; i++) {
      const page = pages[i]
      const thumbnail = await this.createThumbnailElement(page, i)
      
      if (thumbnail) {
        this.gridTarget.appendChild(thumbnail)
        this.thumbnailElements.push(thumbnail)
      }
    }
    
    this.logger.debug(`Generated ${this.thumbnailElements.length} thumbnails`)
  }
  
  async createThumbnailElement(page, index) {
    try {
      // Find the main image in the page
      const img = page.querySelector("img")
      if (!img) return null
      
      const thumbnail = document.createElement("div")
      thumbnail.className = "bb-thumbnail"
      thumbnail.setAttribute("data-bookblock-thumbnail-target", "thumbnail")
      thumbnail.setAttribute("data-page", index)
      thumbnail.setAttribute("data-action", "click->bookblock-thumbnail#selectThumbnail")
      thumbnail.setAttribute("tabindex", "0")
      thumbnail.setAttribute("role", "button")
      thumbnail.setAttribute("aria-label", `Go to page ${index + 1}`)
      
      const thumbnailImg = document.createElement("img")
      thumbnailImg.className = "bb-thumbnail-image"
      thumbnailImg.alt = `Page ${index + 1} thumbnail`
      thumbnailImg.setAttribute("data-page", index)
      
      // Lazy loading support
      if (this.lazyLoadValue && "loading" in HTMLImageElement.prototype) {
        thumbnailImg.loading = "lazy"
        thumbnailImg.decoding = "async"
      }
      
      // Set thumbnail source (same as main image for now)
      // In a real implementation, you might want to generate smaller thumbnails
      if (this.lazyLoadValue && index > 5) {
        thumbnailImg.setAttribute("data-src", img.src)
        thumbnailImg.setAttribute("data-loaded", "false")
        thumbnail.classList.add(this.loadingClass)
      } else {
        thumbnailImg.src = img.src
        thumbnailImg.setAttribute("data-loaded", "true")
      }
      
      // Add loading event listener
      thumbnailImg.addEventListener("load", () => {
        thumbnailImg.setAttribute("data-loaded", "true")
        thumbnail.classList.remove(this.loadingClass)
      })
      
      // Create label
      const label = document.createElement("div")
      label.className = "bb-thumbnail-label"
      label.textContent = `${index + 1}`
      
      thumbnail.appendChild(thumbnailImg)
      thumbnail.appendChild(label)
      
      return thumbnail
      
    } catch (error) {
      this.logger.error(`Failed to create thumbnail for page ${index}`, error)
      return null
    }
  }
  
  collectExistingThumbnails() {
    this.thumbnailElements = Array.from(this.thumbnailTargets)
    this.logger.debug(`Collected ${this.thumbnailElements.length} existing thumbnails`)
  }
  
  setupThumbnailEvents() {
    this.thumbnailElements.forEach((thumbnail, index) => {
      // Keyboard support
      thumbnail.addEventListener("keydown", (event) => {
        if (event.key === "Enter" || event.key === " ") {
          event.preventDefault()
          this.selectThumbnail({ currentTarget: thumbnail })
        }
      })
      
      // Intersection Observer for lazy loading
      if (this.lazyLoadValue) {
        this.observeThumbnail(thumbnail)
      }
    })
  }
  
  observeThumbnail(thumbnail) {
    if (!window.IntersectionObserver) return
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.loadThumbnail(entry.target)
          observer.unobserve(entry.target)
        }
      })
    }, {
      rootMargin: "50px"
    })
    
    observer.observe(thumbnail)
  }
  
  loadThumbnail(thumbnail) {
    const img = thumbnail.querySelector("img[data-src]")
    if (img && img.dataset.src) {
      img.src = img.dataset.src
      img.removeAttribute("data-src")
    }
  }
  
  setupNavigation() {
    // Setup scroll navigation if needed
    if (this.hasGridTarget) {
      this.setupScrollNavigation()
    }
  }
  
  setupScrollNavigation() {
    // Add scroll event listener for navigation updates
    if (this.hasGridTarget) {
      this.gridTarget.addEventListener("scroll", this.handleScroll.bind(this))
    }
  }
  
  setupKeyboard() {
    this.keyboardHandler = this.handleKeyboard.bind(this)
    document.addEventListener("keydown", this.keyboardHandler)
  }
  
  setupTouch() {
    if (!this.hasGridTarget) return
    
    // Touch scroll support
    this.gridTarget.classList.add("bb-thumbnails-touch")
    
    // Swipe support
    let touchStartX = null
    
    this.gridTarget.addEventListener("touchstart", (event) => {
      touchStartX = event.touches[0].clientX
    }, { passive: true })
    
    this.gridTarget.addEventListener("touchend", (event) => {
      if (!touchStartX) return
      
      const touchEndX = event.changedTouches[0].clientX
      const deltaX = touchEndX - touchStartX
      
      if (Math.abs(deltaX) > 50) {
        if (deltaX > 0) {
          this.scrollPrev()
        } else {
          this.scrollNext()
        }
      }
      
      touchStartX = null
    }, { passive: true })
  }
  
  // Public Actions (Stimulus Actions)
  toggle(event) {
    event?.preventDefault()
    
    if (this.visibleValue) {
      this.hide()
    } else {
      this.show()
    }
  }
  
  show() {
    this.visibleValue = true
    
    if (this.hasThumbnailsTarget) {
      this.thumbnailsTarget.classList.add(this.visibleClass)
    }
    
    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute("aria-expanded", "true")
    }
    
    // Load visible thumbnails
    this.loadVisibleThumbnails()
    
    // Update counter
    this.updateCounter()
    
    this.dispatch("shown")
  }
  
  hide() {
    this.visibleValue = false
    
    if (this.hasThumbnailsTarget) {
      this.thumbnailsTarget.classList.remove(this.visibleClass)
    }
    
    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute("aria-expanded", "false")
    }
    
    this.dispatch("hidden")
  }
  
  selectThumbnail(event) {
    const thumbnail = event.currentTarget
    const pageIndex = parseInt(thumbnail.getAttribute("data-page") || "0", 10)
    
    this.goToPage(pageIndex)
  }
  
  goToPage(pageIndex) {
    // Update active thumbnail
    this.setActiveThumbnail(pageIndex)
    
    // Notify BookBlock controller
    this.dispatch("pageSelected", { 
      detail: { pageIndex },
      bubbles: true 
    })
    
    // Scroll to show active thumbnail
    this.scrollToThumbnail(pageIndex)
  }
  
  scrollPrev() {
    if (!this.hasGridTarget) return
    
    const scrollAmount = this.getScrollStep()
    const newScrollLeft = Math.max(0, this.gridTarget.scrollLeft - scrollAmount)
    
    this.smoothScrollTo(newScrollLeft)
    this.updateNavigation()
  }
  
  scrollNext() {
    if (!this.hasGridTarget) return
    
    const scrollAmount = this.getScrollStep()
    const maxScroll = this.gridTarget.scrollWidth - this.gridTarget.clientWidth
    const newScrollLeft = Math.min(maxScroll, this.gridTarget.scrollLeft + scrollAmount)
    
    this.smoothScrollTo(newScrollLeft)
    this.updateNavigation()
  }
  
  // Thumbnail Management
  setActiveThumbnail(pageIndex) {
    this.currentIndex = pageIndex
    
    // Remove active class from all thumbnails
    this.thumbnailElements.forEach(thumbnail => {
      thumbnail.classList.remove(this.activeClass)
      thumbnail.setAttribute("aria-selected", "false")
    })
    
    // Add active class to current thumbnail
    if (this.thumbnailElements[pageIndex]) {
      this.thumbnailElements[pageIndex].classList.add(this.activeClass)
      this.thumbnailElements[pageIndex].setAttribute("aria-selected", "true")
    }
    
    this.updateCounter()
  }
  
  scrollToThumbnail(pageIndex) {
    if (!this.hasGridTarget || !this.thumbnailElements[pageIndex]) return
    
    const thumbnail = this.thumbnailElements[pageIndex]
    const gridRect = this.gridTarget.getBoundingClientRect()
    const thumbnailRect = thumbnail.getBoundingClientRect()
    
    // Check if thumbnail is visible
    const isVisible = thumbnailRect.left >= gridRect.left && 
                     thumbnailRect.right <= gridRect.right
    
    if (!isVisible) {
      // Scroll to make thumbnail visible
      const scrollLeft = thumbnail.offsetLeft - (this.gridTarget.clientWidth / 2) + (thumbnail.clientWidth / 2)
      this.smoothScrollTo(scrollLeft)
    }
  }
  
  smoothScrollTo(scrollLeft) {
    if (!this.hasGridTarget) return
    
    this.gridTarget.scrollTo({
      left: scrollLeft,
      behavior: "smooth"
    })
  }
  
  getScrollStep() {
    if (!this.hasGridTarget) return 0
    
    // Calculate scroll step based on thumbnail width
    const thumbnailWidth = this.thumbnailElements[0]?.clientWidth || 100
    return thumbnailWidth * this.scrollStepValue
  }
  
  loadVisibleThumbnails() {
    if (!this.lazyLoadValue || !this.hasGridTarget) return
    
    const gridRect = this.gridTarget.getBoundingClientRect()
    
    this.thumbnailElements.forEach(thumbnail => {
      const thumbnailRect = thumbnail.getBoundingClientRect()
      const isVisible = thumbnailRect.left < gridRect.right && 
                       thumbnailRect.right > gridRect.left
      
      if (isVisible) {
        this.loadThumbnail(thumbnail)
      }
    })
  }
  
  // Event Handlers
  handlePageChanged(event) {
    const { currentPage } = event.detail
    this.setActiveThumbnail(currentPage)
  }
  
  handleKeyboard(event) {
    if (!this.visibleValue) return
    
    // Only handle if thumbnails have focus
    if (!this.element.contains(document.activeElement)) return
    
    switch (event.key) {
      case "ArrowLeft":
        event.preventDefault()
        this.focusPrevThumbnail()
        break
      case "ArrowRight":
        event.preventDefault()
        this.focusNextThumbnail()
        break
      case "Home":
        event.preventDefault()
        this.focusThumbnail(0)
        break
      case "End":
        event.preventDefault()
        this.focusThumbnail(this.thumbnailElements.length - 1)
        break
      case "Escape":
        event.preventDefault()
        this.hide()
        break
    }
  }
  
  handleScroll() {
    this.updateNavigation()
    this.loadVisibleThumbnails()
  }
  
  // Focus Management
  focusPrevThumbnail() {
    const focusedIndex = this.getFocusedThumbnailIndex()
    if (focusedIndex > 0) {
      this.focusThumbnail(focusedIndex - 1)
    }
  }
  
  focusNextThumbnail() {
    const focusedIndex = this.getFocusedThumbnailIndex()
    if (focusedIndex < this.thumbnailElements.length - 1) {
      this.focusThumbnail(focusedIndex + 1)
    }
  }
  
  focusThumbnail(index) {
    if (this.thumbnailElements[index]) {
      this.thumbnailElements[index].focus()
      this.scrollToThumbnail(index)
    }
  }
  
  getFocusedThumbnailIndex() {
    const focusedElement = document.activeElement
    return this.thumbnailElements.findIndex(thumb => thumb === focusedElement)
  }
  
  // UI Updates
  updateNavigation() {
    if (!this.hasGridTarget) return
    
    const canScrollLeft = this.gridTarget.scrollLeft > 0
    const canScrollRight = this.gridTarget.scrollLeft < 
      (this.gridTarget.scrollWidth - this.gridTarget.clientWidth)
    
    // Update navigation buttons
    if (this.hasPrevButtonTarget) {
      this.prevButtonTarget.disabled = !canScrollLeft
      this.prevButtonTarget.setAttribute("aria-disabled", !canScrollLeft)
    }
    
    if (this.hasNextButtonTarget) {
      this.nextButtonTarget.disabled = !canScrollRight
      this.nextButtonTarget.setAttribute("aria-disabled", !canScrollRight)
    }
  }
  
  updateCounter() {
    if (this.hasCounterTarget) {
      this.counterTarget.textContent = `${this.currentIndex + 1} / ${this.thumbnailElements.length}`
    }
  }
  
  updateThumbnailsState() {
    // Set initial visibility state
    if (this.visibleValue) {
      this.show()
    } else {
      this.hide()
    }
    
    // Set initial active thumbnail
    this.setActiveThumbnail(this.currentIndex)
    
    // Update navigation
    this.updateNavigation()
  }
  
  // Cleanup
  cleanup() {
    if (this.keyboardHandler) {
      document.removeEventListener("keydown", this.keyboardHandler)
    }
    
    // Clean up intersection observers
    // (they will be automatically cleaned up when elements are removed)
  }
  
  // Public API
  getThumbnailCount() {
    return this.thumbnailElements.length
  }
  
  getCurrentIndex() {
    return this.currentIndex
  }
  
  isVisible() {
    return this.visibleValue
  }
  
  addThumbnail(pageElement, index) {
    // Dynamically add a new thumbnail
    this.createThumbnailElement(pageElement, index).then(thumbnail => {
      if (thumbnail) {
        this.gridTarget.appendChild(thumbnail)
        this.thumbnailElements.push(thumbnail)
        this.setupThumbnailEvents()
        this.updateCounter()
        this.updateNavigation()
      }
    })
  }
  
  removeThumbnail(index) {
    // Dynamically remove a thumbnail
    if (this.thumbnailElements[index]) {
      this.thumbnailElements[index].remove()
      this.thumbnailElements.splice(index, 1)
      this.updateCounter()
      this.updateNavigation()
    }
  }
}