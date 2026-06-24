import { Directive, ElementRef, HostBinding, HostListener, Renderer2, inject } from '@angular/core';

@Directive({
  selector: '[dgHorizontalRail]',
  standalone: true,
})
export class HorizontalRailDirective {
  private readonly elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly renderer = inject(Renderer2);

  private dragPointerId: number | null = null;
  private dragStartX = 0;
  private dragStartScrollLeft = 0;

  @HostBinding('class.dg-horizontal-rail--dragging')
  protected isDragging = false;

  constructor() {
    const element = this.elementRef.nativeElement;
    if (!element.hasAttribute('tabindex')) {
      this.renderer.setAttribute(element, 'tabindex', '0');
    }
  }

  @HostListener('wheel', ['$event'])
  onWheel(event: WheelEvent): void {
    const rail = this.elementRef.nativeElement;
    if (!this.isOverflowing(rail)) {
      return;
    }

    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
    if (delta === 0) {
      return;
    }

    event.preventDefault();
    rail.scrollLeft += delta;
  }

  @HostListener('pointerdown', ['$event'])
  onPointerDown(event: PointerEvent): void {
    const rail = this.elementRef.nativeElement;
    if (!this.isOverflowing(rail) || (event.pointerType !== 'mouse' && event.pointerType !== 'pen')) {
      return;
    }

    this.dragPointerId = event.pointerId;
    this.dragStartX = event.clientX;
    this.dragStartScrollLeft = rail.scrollLeft;
    this.isDragging = true;
    rail.setPointerCapture(event.pointerId);
    event.preventDefault();
  }

  @HostListener('pointermove', ['$event'])
  onPointerMove(event: PointerEvent): void {
    const rail = this.elementRef.nativeElement;
    if (!this.isDragging || this.dragPointerId !== event.pointerId) {
      return;
    }

    const deltaX = event.clientX - this.dragStartX;
    rail.scrollLeft = this.dragStartScrollLeft - deltaX;
    event.preventDefault();
  }

  @HostListener('pointerup', ['$event'])
  @HostListener('pointercancel', ['$event'])
  onPointerEnd(event: PointerEvent): void {
    this.endDrag(event.pointerId);
  }

  @HostListener('keydown', ['$event'])
  onKeyDown(event: KeyboardEvent): void {
    const rail = this.elementRef.nativeElement;
    if (!this.isOverflowing(rail)) {
      return;
    }

    const pageAmount = Math.max(rail.clientWidth * 0.85, 240);
    let handled = true;

    switch (event.key) {
      case 'ArrowLeft':
        rail.scrollLeft -= 80;
        break;
      case 'ArrowRight':
        rail.scrollLeft += 80;
        break;
      case 'PageUp':
        rail.scrollLeft -= pageAmount;
        break;
      case 'PageDown':
        rail.scrollLeft += pageAmount;
        break;
      case 'Home':
        rail.scrollLeft = 0;
        break;
      case 'End':
        rail.scrollLeft = rail.scrollWidth;
        break;
      default:
        handled = false;
    }

    if (handled) {
      event.preventDefault();
    }
  }

  private endDrag(pointerId: number): void {
    const rail = this.elementRef.nativeElement;
    if (this.dragPointerId !== pointerId) {
      return;
    }

    if (rail.hasPointerCapture(pointerId)) {
      rail.releasePointerCapture(pointerId);
    }

    this.dragPointerId = null;
    this.isDragging = false;
  }

  private isOverflowing(element: HTMLElement): boolean {
    return element.scrollWidth > element.clientWidth;
  }
}
