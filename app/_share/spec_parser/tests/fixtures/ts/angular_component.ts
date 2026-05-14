// @spec
// File-level spec: app entry component for the demo Angular app.
// @end-spec

// @spec
// AppComponent mounts at /app-root and renders the active user.
// @end-spec
@Component({
    selector: "app-root",
    templateUrl: "./app.component.html",
})
export class AppComponent
{
    // @spec
    // current user, null when logged out
    // @end-spec
    user: User | null = null;

    // @spec
    // remembered route the user clicked before login
    // @end-spec
    next_url: string = "";

    // @spec
    // Logs the user in via the auth service.
    // throws AuthError on bad credentials
    // @end-spec
    @LogCall()
    async login(username: string, password: string): Promise<void>
    {
        await this.auth.sign_in(username, password);
    }

    // @spec
    // Sign the active user out and clear the remembered route.
    // @end-spec
    logout(): void
    {
        this.user = null;
        this.next_url = "";
    }
}
