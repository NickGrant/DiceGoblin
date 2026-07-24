import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { resolveApiBaseUrl } from '../../core/config/runtime-config';
import { SessionService } from '../../core/services/session/session.service';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';

@Component({
  selector: 'app-landing-page',
  standalone: true,
  imports: [DgCommandBtnDirective, FormsModule, RouterLink],
  templateUrl: './landing-page.component.html',
  styleUrl: './landing-page.component.scss',
})
export class LandingPageComponent {
  readonly discordLoginUrl = `${resolveApiBaseUrl()}/auth/discord/start`;
  readonly authMode = signal<'login' | 'register' | 'forgot' | 'reset'>('login');
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly success = signal<string | null>(null);

  email = '';
  password = '';
  displayName = '';
  resetToken = '';
  resetTokenHint = '';

  constructor(private readonly sessionService: SessionService) {}

  setAuthMode(mode: 'login' | 'register' | 'forgot' | 'reset'): void {
    this.authMode.set(mode);
    this.error.set(null);
    this.success.set(null);
    if (mode !== 'reset') {
      this.resetTokenHint = '';
    }
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
        return 'Create Account';
      case 'forgot':
        return 'Reset Password';
      case 'reset':
        return 'Save Password';
      default:
        return 'Sign In';
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

