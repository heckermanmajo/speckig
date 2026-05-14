// @spec
// File-level spec: Angular auth service stub for the demo app.
// @end-spec

// @spec
// FooService coordinates auth flows and wraps the HTTP client.
// @end-spec
@Injectable()
export class FooService
{
    // @spec
    // Construct the service with the injected HTTP client.
    // @end-spec
    constructor(private http: HttpClient)
    {
    }

    // @spec
    // Sign the user in via the backend and return the auth token.
    // @end-spec
    sign_in(username: string, password: string): Promise<string>
    {
        return this.http.post("/api/sign-in", {username, password});
    }

    // @spec
    // Sign the current user out, clearing the cached token.
    // @end-spec
    sign_out(): void
    {
        this.http.post("/api/sign-out", {});
    }
}
