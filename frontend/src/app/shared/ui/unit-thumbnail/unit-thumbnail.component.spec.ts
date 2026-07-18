import { ComponentFixture, TestBed } from '@angular/core/testing';
import { UnitThumbnailComponent } from './unit-thumbnail.component';

describe('UnitThumbnailComponent', () => {
  let fixture: ComponentFixture<UnitThumbnailComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UnitThumbnailComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UnitThumbnailComponent);
    fixture.componentRef.setInput('unit', {
      id: 'u1',
      name: 'Fang',
      level: 3,
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Bruiser',
    });
  });

  it('renders the unit name, level, and type thumbnail', () => {
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.textContent).toContain('Fang');
    expect(host.textContent).toContain('Lv 3');
    expect(host.querySelector('img')?.getAttribute('src')).toContain('/assets/ui/units/thumbnails/goblin/bruiser.png');
    expect(host.querySelector('.unit-thumbnail')?.getAttribute('title')).toContain('Bruiser');
  });
});
