import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { resolveApiBaseUrl } from '../../core/config/runtime-config';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgButtonDirective } from '../../shared/ui/dg-button/dg-button.directive';
import { DgChipDirective } from '../../shared/ui/dg-chip/dg-chip.directive';
import { TabStripComponent, TabStripItem } from '../../shared/ui/tab-strip/tab-strip.component';

type AuthMode = 'login' | 'register' | 'forgot' | 'reset';

@Component({
  selector: 'app-landing-page',
  standalone: true,
  imports: [
    DgAlertComponent,
    DgButtonDirective,
    DgChipDirective,
    FormsModule,
    RouterLink,
    TabStripComponent,
  ],
  templateUrl: './landing-page.component.html',
  styleUrl: './landing-page.component.scss',
})
export class LandingPageComponent {
  readonly discordLoginUrl = `${resolveApiBaseUrl()}/auth/discord/start`;
  readonly authMode = signal<AuthMode>('login');
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly success = signal<string | null>(null);
  readonly rotationReminderDismissed = signal(false);
  readonly authTabs: ReadonlyArray<TabStripItem> = [
    { id: 'login', label: 'Sign In' },
    { id: 'register', label: 'Register' },
    { id: 'forgot', label: 'Recovery' },
  ];

  email = '';
  password = '';
  displayName = '';
  resetToken = '';
  resetTokenHint = '';

  constructor(private readonly sessionService: SessionService) {}

  setAuthMode(mode: AuthMode): void {
    this.authMode.set(mode);
    this.error.set(null);
    this.success.set(null);
    if (mode !== 'reset') {
      this.resetTokenHint = '';
    }
  }

  selectAuthMode(mode: string): void {
    if (mode === 'login' || mode === 'register' || mode === 'forgot') {
      this.setAuthMode(mode);
    }
  }

  dismissRotationReminder(): void {
    this.rotationReminderDismissed.set(true);
  }

  async submitLocalAuth(): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    this.success.set(null);

    try {
      if (this.authMode() === 'register') {
        await this.sessionService.registerWithLocalCredentials(
          this.email,
          this.password,
          this.displayName,
        );
      } else if (this.authMode() === 'forgot') {
        const response = await this.sessionService.requestPasswordReset(this.email);
        this.success.set(response.message);
        if (response.reset_token) {
          this.resetToken = response.reset_token;
          this.resetTokenHint = response.reset_token;
          this.authMode.set('reset');
        }
      } else if (this.authMode() === 'reset') {
        await this.sessionService.confirmPasswordReset(this.resetToken, this.password);
      } else {
        await this.sessionService.loginWithLocalCredentials(this.email, this.password);
      }
    } catch {
      this.error.set(this.errorMessageForMode());
    } finally {
      this.busy.set(false);
    }
  }

  passwordAutocomplete(): string {
    return this.authMode() === 'login' ? 'current-password' : 'new-password';
  }

  submitLabel(): string {
    switch (this.authMode()) {
      case 'register':
        return 'Claim Warband';
      case 'forgot':
        return 'Recover Access';
      case 'reset':
        return 'Save Password';
      default:
        return 'Enter the Chaos';
    }
  }

  private errorMessageForMode(): string {
    switch (this.authMode()) {
      case 'register':
        return 'Could not create that account.';
      case 'forgot':
        return 'Could not request a reset.';
      case 'reset':
        return 'That reset token is invalid or expired.';
      default:
        return 'Email or password is incorrect.';
    }
  }
}
