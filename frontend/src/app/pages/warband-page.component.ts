import { NgFor } from '@angular/common';
import { Component, inject } from '@angular/core';
import { SessionService } from '../core/services/session.service';

@Component({
  selector: 'app-warband-page',
  standalone: true,
  imports: [NgFor],
  templateUrl: './warband-page.component.html',
  styleUrl: './warband-page.component.scss',
})
export class WarbandPageComponent {
  private readonly sessionService = inject(SessionService);
  readonly profile = this.sessionService.profile;

  readonly squads = [
    'Frontline Detachment',
    'Mud Marchers',
    'Reserve Sling Crew',
  ];

  readonly units = [
    'Goblin Guardian',
    'Kobold Shieldbearer',
    'Frogman Bruiser',
    'Goblin Deadeye',
    'Pig Mudslinger',
    'Goblin Bannerbearer',
  ];
}
