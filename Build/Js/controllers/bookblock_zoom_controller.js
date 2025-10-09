// BookBlock Zoom Stimulus Controller
// Modern JavaScript implementation for zoom functionality

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = [
    "overlay",
    "container", 
    "image",
    "controls",
    "zoomIn",
    "zoomOut", 
    "zoomReset",
    "zoomClose",
    "info",
    "navigation"
  ]
  
  static values = {
    minZoom: { type: Number, default: 0.5 },
    maxZoom: { type: Number, default: 4 },
    zoomStep: { type: Number, default: 0.5 },
    animationDuration: { type: Number, default: 300 },
    enablePinch: { type: Boolean, default: true },
    enablePan: { type: Boolean, default: true },
    enableKeyboard: { type: Boolean, default: true }
  }
  
  static classes = [
    "active",
    "zooming",
    "panning"
  ]

  // Lifecycle
  connect() {
    this.logger = console
    this.currentZoom = 1
    this.currentImage = null
    this.isDragging = false
    this.lastTouchDistance = null
    this.panStartX = 0
    this.panStartY = 0
    this.panX = 0
    this.panY = 0
    this.isTouch = 'ontouchstart' in window
    
    this.logger.debug("BookBlock Zoom controller connected")
    
    this.setupEventListeners()
  }
  
  disconnect() {
    this.cleanup()
    this.logger.debug("BookBlock Zoom controller disconnected")
  }
  
  setupEventListeners() {
    // Keyboard navigation
    if (this.enableKeyboardValue) {
      this.keyboardHandler = this.handleKeyboard.bind(this)
      document.addEventListener("keydown", this.keyboardHandler)
    }
    
    // Touch/mouse events for pan and zoom
    if (this.hasImageTarget) {
      this.setupImageEvents()
    }
    
    // Window events
    this.resizeHandler = this.handleResize.bind(this)
    window.addEventListener("resize", this.resizeHandler)
  }
  
  setupImageEvents() {
    // Mouse events
    this.imageTarget.addEventListener("wheel", this.handleWheel.bind(this), { passive: false })
    this.imageTarget.addEventListener("mousedown", this.handleMouseDown.bind(this))
    this.imageTarget.addEventListener("mousemove", this.handleMouseMove.bind(this))
    this.imageTarget.addEventListener("mouseup", this.handleMouseUp.bind(this))
    this.imageTarget.addEventListener("mouseleave", this.handleMouseUp.bind(this))
    
    // Touch events
    if (this.isTouch) {
      this.imageTarget.addEventListener("touchstart", this.handleTouchStart.bind(this), { passive: false })
      this.imageTarget.addEventListener("touchmove", this.handleTouchMove.bind(this), { passive: false })
      this.imageTarget.addEventListener("touchend", this.handleTouchEnd.bind(this), { passive: false })
    }
  }
  
  // Public Actions (Stimulus Actions)
  showImage(event) {
    const imageElement = event.currentTarget
    const imageSrc = imageElement.src || imageElement.dataset.src
    
    if (!imageSrc) {
      this.logger.warn("No image source found")
      return
    }
    
    this.openZoom(imageSrc, imageElement)
  }
  
  zoomIn(event) {
    event?.preventDefault()
    this.zoom(this.currentZoom + this.zoomStepValue)
  }
  
  zoomOut(event) {
    event?.preventDefault()
    this.zoom(this.currentZoom - this.zoomStepValue)
  }
  
  zoomReset(event) {
    event?.preventDefault()
    this.resetZoom()
  }
  
  close(event) {
    event?.preventDefault()
    this.closeZoom()
  }
  
  // Zoom Management
  async openZoom(imageSrc, originalElement = null) {
    try {
      this.dispatch("zoomOpening", { detail: { imageSrc, originalElement } })
      
      // Show overlay
      if (this.hasOverlayTarget) {
        this.overlayTarget.classList.add(this.activeClass)
      }
      
      // Load image
      await this.loadImage(imageSrc)
      
      // Reset zoom state
      this.resetZoomState()
      
      // Setup image
      this.setupZoomImage()
      
      // Update controls
      this.updateControls()
      
      // Focus management
      this.manageZoomFocus(true)
      
      this.dispatch("zoomOpened", { detail: { imageSrc, zoom: this.currentZoom } })
      
    } catch (error) {
      this.logger.error("Failed to open zoom", error)
      this.dispatch("zoomError", { detail: { error, imageSrc } })
    }
  }
  
  closeZoom() {
    this.dispatch("zoomClosing")
    
    // Hide overlay
    if (this.hasOverlayTarget) {
      this.overlayTarget.classList.remove(this.activeClass)
    }
    
    // Reset state
    this.resetZoomState()
    
    // Clear image
    if (this.hasImageTarget) {
      this.imageTarget.style.transform = ""
      this.imageTarget.src = ""
    }
    
    // Focus management
    this.manageZoomFocus(false)
    
    this.dispatch("zoomClosed")
  }
  
  async loadImage(imageSrc) {
    return new Promise((resolve, reject) => {
      if (!this.hasImageTarget) {
        reject(new Error("No image target available"))
        return
      }
      
      const img = this.imageTarget
      
      const handleLoad = () => {
        img.removeEventListener("load", handleLoad)
        img.removeEventListener("error", handleError)
        this.currentImage = img
        resolve(img)
      }
      
      const handleError = () => {
        img.removeEventListener("load", handleLoad)
        img.removeEventListener("error", handleError)
        reject(new Error("Failed to load image"))
      }
      
      img.addEventListener("load", handleLoad)
      img.addEventListener("error", handleError)
      
      img.src = imageSrc
    })
  }
  
  setupZoomImage() {
    if (!this.currentImage) return
    
    // Set initial zoom level
    this.currentImage.setAttribute("data-zoom", "1")
    this.currentImage.style.transformOrigin = "center center"
    
    // Update info display
    this.updateZoomInfo()
  }
  
  zoom(newZoom) {
    newZoom = Math.max(this.minZoomValue, Math.min(this.maxZoomValue, newZoom))
    
    if (newZoom === this.currentZoom) return
    
    const oldZoom = this.currentZoom
    this.currentZoom = newZoom
    
    this.dispatch("zoomChanging", { detail: { oldZoom, newZoom } })
    
    this.applyZoom()
    this.updateControls()
    this.updateZoomInfo()
    
    this.dispatch("zoomChanged", { detail: { zoom: newZoom } })
  }
  
  applyZoom() {
    if (!this.currentImage) return
    
    const transform = `scale(${this.currentZoom}) translate(${this.panX}px, ${this.panY}px)`
    
    this.currentImage.style.transform = transform
    this.currentImage.setAttribute("data-zoom", this.currentZoom.toString())
    
    // Add transition for smooth zooming
    this.currentImage.style.transition = `transform ${this.animationDurationValue}ms ease-out`
    
    // Remove transition after animation
    setTimeout(() => {
      if (this.currentImage) {
        this.currentImage.style.transition = ""
      }
    }, this.animationDurationValue)
  }
  
  resetZoom() {
    this.currentZoom = 1
    this.panX = 0
    this.panY = 0
    
    this.applyZoom()
    this.updateControls()
    this.updateZoomInfo()
    
    this.dispatch("zoomReset")
  }
  
  resetZoomState() {
    this.currentZoom = 1
    this.panX = 0
    this.panY = 0
    this.isDragging = false
    this.lastTouchDistance = null
  }
  
  // Pan Management
  startPan(clientX, clientY) {
    if (this.currentZoom <= 1) return false
    
    this.isDragging = true
    this.panStartX = clientX - this.panX
    this.panStartY = clientY - this.panY
    
    if (this.currentImage) {
      this.currentImage.style.cursor = "grabbing"
    }
    
    this.element.classList.add(this.panningClass)
    
    return true
  }
  
  updatePan(clientX, clientY) {
    if (!this.isDragging || this.currentZoom <= 1) return
    
    const newPanX = clientX - this.panStartX
    const newPanY = clientY - this.panStartY
    
    // Constrain panning to image bounds
    const constrainedPan = this.constrainPan(newPanX, newPanY)
    this.panX = constrainedPan.x
    this.panY = constrainedPan.y
    
    this.applyZoom()
  }
  
  endPan() {
    this.isDragging = false
    
    if (this.currentImage) {
      this.currentImage.style.cursor = this.currentZoom > 1 ? "grab" : "zoom-in"
    }
    
    this.element.classList.remove(this.panningClass)
  }
  
  constrainPan(panX, panY) {
    if (!this.currentImage || this.currentZoom <= 1) {
      return { x: 0, y: 0 }
    }
    
    const containerRect = this.hasContainerTarget ? 
      this.containerTarget.getBoundingClientRect() : 
      this.element.getBoundingClientRect()
      
    const imageRect = this.currentImage.getBoundingClientRect()
    
    const scaledWidth = imageRect.width
    const scaledHeight = imageRect.height
    
    const maxPanX = Math.max(0, (scaledWidth - containerRect.width) / 2)
    const maxPanY = Math.max(0, (scaledHeight - containerRect.height) / 2)
    
    return {
      x: Math.max(-maxPanX, Math.min(maxPanX, panX)),
      y: Math.max(-maxPanY, Math.min(maxPanY, panY))
    }
  }
  
  // Event Handlers
  handleKeyboard(event) {
    if (!this.hasOverlayTarget || !this.overlayTarget.classList.contains(this.activeClass)) {
      return
    }
    
    switch (event.key) {
      case "+":
      case "=":
        event.preventDefault()
        this.zoomIn()
        break
      case "-":
        event.preventDefault()
        this.zoomOut()
        break
      case "0":
        event.preventDefault()
        this.resetZoom()
        break
      case "Escape":
        event.preventDefault()
        this.closeZoom()
        break
      case "ArrowLeft":
        event.preventDefault()
        this.panX += 50
        this.applyZoom()
        break
      case "ArrowRight":
        event.preventDefault()
        this.panX -= 50
        this.applyZoom()
        break
      case "ArrowUp":
        event.preventDefault()
        this.panY += 50
        this.applyZoom()
        break
      case "ArrowDown":
        event.preventDefault()
        this.panY -= 50
        this.applyZoom()
        break
    }
  }
  
  handleWheel(event) {
    if (!this.currentImage) return
    
    event.preventDefault()
    
    const delta = event.deltaY > 0 ? -this.zoomStepValue : this.zoomStepValue
    this.zoom(this.currentZoom + delta)
  }
  
  handleMouseDown(event) {
    event.preventDefault()
    
    if (this.startPan(event.clientX, event.clientY)) {
      // Add global mouse event listeners
      document.addEventListener("mousemove", this.handleMouseMove.bind(this))
      document.addEventListener("mouseup", this.handleMouseUp.bind(this))
    }
  }
  
  handleMouseMove(event) {
    this.updatePan(event.clientX, event.clientY)
  }
  
  handleMouseUp(event) {
    this.endPan()
    
    // Remove global mouse event listeners
    document.removeEventListener("mousemove", this.handleMouseMove.bind(this))
    document.removeEventListener("mouseup", this.handleMouseUp.bind(this))
  }
  
  handleTouchStart(event) {
    event.preventDefault()
    
    if (event.touches.length === 1) {
      // Single touch - pan
      const touch = event.touches[0]
      this.startPan(touch.clientX, touch.clientY)
    } else if (event.touches.length === 2 && this.enablePinchValue) {
      // Two touches - pinch zoom
      this.lastTouchDistance = this.getTouchDistance(event.touches)
    }
  }
  
  handleTouchMove(event) {
    event.preventDefault()
    
    if (event.touches.length === 1) {
      // Single touch - pan
      const touch = event.touches[0]
      this.updatePan(touch.clientX, touch.clientY)
    } else if (event.touches.length === 2 && this.enablePinchValue) {
      // Two touches - pinch zoom
      this.handlePinchZoom(event.touches)
    }
  }
  
  handleTouchEnd(event) {
    event.preventDefault()
    
    if (event.touches.length === 0) {
      this.endPan()
      this.lastTouchDistance = null
    }
  }
  
  handlePinchZoom(touches) {
    const currentDistance = this.getTouchDistance(touches)
    
    if (this.lastTouchDistance && currentDistance) {
      const scale = currentDistance / this.lastTouchDistance
      const newZoom = this.currentZoom * scale
      
      this.zoom(newZoom)
      this.lastTouchDistance = currentDistance
    }
  }
  
  getTouchDistance(touches) {
    if (touches.length < 2) return null
    
    const touch1 = touches[0]
    const touch2 = touches[1]
    
    const deltaX = touch2.clientX - touch1.clientX
    const deltaY = touch2.clientY - touch1.clientY
    
    return Math.sqrt(deltaX * deltaX + deltaY * deltaY)
  }
  
  handleResize() {
    if (this.currentZoom > 1) {
      // Re-constrain pan on resize
      const constrainedPan = this.constrainPan(this.panX, this.panY)
      this.panX = constrainedPan.x
      this.panY = constrainedPan.y
      this.applyZoom()
    }
  }
  
  // UI Updates
  updateControls() {
    // Update zoom in/out button states
    if (this.hasZoomInTarget) {
      this.zoomInTarget.disabled = this.currentZoom >= this.maxZoomValue
      this.zoomInTarget.setAttribute("aria-disabled", this.zoomInTarget.disabled)
    }
    
    if (this.hasZoomOutTarget) {
      this.zoomOutTarget.disabled = this.currentZoom <= this.minZoomValue
      this.zoomOutTarget.setAttribute("aria-disabled", this.zoomOutTarget.disabled)
    }
    
    // Update cursor
    if (this.currentImage) {
      this.currentImage.style.cursor = this.currentZoom > 1 ? "grab" : "zoom-in"
    }
  }
  
  updateZoomInfo() {
    if (this.hasInfoTarget) {
      const zoomPercent = Math.round(this.currentZoom * 100)
      this.infoTarget.textContent = `${zoomPercent}%`
    }
  }
  
  manageZoomFocus(isOpening) {
    if (isOpening) {
      // Store currently focused element
      this.previouslyFocused = document.activeElement
      
      // Focus the overlay
      if (this.hasOverlayTarget) {
        this.overlayTarget.focus()
      }
    } else {
      // Restore focus
      if (this.previouslyFocused && this.previouslyFocused.focus) {
        this.previouslyFocused.focus()
      }
    }
  }
  
  // Cleanup
  cleanup() {
    if (this.keyboardHandler) {
      document.removeEventListener("keydown", this.keyboardHandler)
    }
    
    if (this.resizeHandler) {
      window.removeEventListener("resize", this.resizeHandler)
    }
    
    // Clean up any global event listeners
    document.removeEventListener("mousemove", this.handleMouseMove.bind(this))
    document.removeEventListener("mouseup", this.handleMouseUp.bind(this))
  }
  
  // Public API
  getZoom() {
    return this.currentZoom
  }
  
  setZoom(zoom) {
    this.zoom(zoom)
  }
  
  getCurrentImage() {
    return this.currentImage
  }
  
  isZoomOpen() {
    return this.hasOverlayTarget && this.overlayTarget.classList.contains(this.activeClass)
  }
}