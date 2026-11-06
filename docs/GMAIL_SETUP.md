# Connecting Gmail

JobHunt reads Gmail with your own Google OAuth client. It only asks for `gmail.readonly`, and only saves the sender, subject, snippet and date of job-related email from the last 30 days.

You need a Google account to create the client. It can be the same account you connect.

## 1. Create a Google Cloud project

1. Open https://console.cloud.google.com and sign in.
2. Open the project picker at the top of the page, click **New project**, name it `JobHunt`, and click **Create**.
3. Make sure `JobHunt` is the selected project before continuing.

## 2. Turn on the Gmail API

1. Go to https://console.cloud.google.com/apis/library/gmail.googleapis.com
2. Click **Enable**.

## 3. Set up the consent screen

1. Go to https://console.cloud.google.com/auth/overview and click **Get started**.
2. **App information**: app name `JobHunt`, and pick your email as the support email. Click **Next**.
3. **Audience**: choose **External**. Click **Next**.
4. **Contact information**: enter your email. Click **Next**.
5. Agree to the user data policy, then click **Continue** and **Create**.

## 4. Add yourself as a test user

1. Go to **Audience** (https://console.cloud.google.com/auth/audience).
2. Leave the publishing status as **Testing**.
3. Under **Test users**, click **Add users**, enter every Gmail address you want to connect, and click **Save**.

## 5. Allow read-only Gmail access

1. Go to **Data Access** (https://console.cloud.google.com/auth/scopes).
2. Click **Add or remove scopes**.
3. Filter for `gmail.readonly`, tick `.../auth/gmail.readonly`, and click **Update**.
4. Click **Save**.

## 6. Create the OAuth client

1. Go to **Clients** (https://console.cloud.google.com/auth/clients) and click **Create client**.
2. Application type: **Web application**. Name: `JobHunt local`.
3. Under **Authorized redirect URIs**, click **Add URI** and paste exactly:

   ```
   http://127.0.0.1:8000/settings/gmail/callback
   ```

4. Click **Create**.
5. Copy the **Client ID** and **Client secret** now. Google only shows the full secret once, so also click **Download JSON** and keep the file somewhere safe outside this project.

## 7. Add the client to JobHunt

Open `backend/.env` and fill in:

```
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
```

## 8. Connect

1. Start the app with `npm run dev` and open http://127.0.0.1:5180. Use `127.0.0.1`, not `localhost`: Google sends you back to the backend at `127.0.0.1:8000`, and the sign-in cookie only carries over on the same address.
2. Go to **Settings** and click **Connect a Gmail account**.
3. Google warns that it hasn't verified the app. This is expected for your own app in Testing. Click **Continue**.
4. Allow **Read your email messages and settings** (tick the box if Google shows one), then click **Continue**.
5. Back in JobHunt, click **Sync now**.

Repeat **Connect a Gmail account** for each extra account. Each one must be listed as a test user.

## Good to know

- **Weekly reconnect:** while the app is in Testing, Google expires the connection after 7 days. Settings then shows **Needs reconnecting**. Click **Reconnect** to fix it. Changing your Google password also ends the connection.
- **Hourly sync:** `npm run dev` runs the backend, the scheduler and the website together, so mail syncs every hour while it is running. `php backend/artisan serve` on its own does not sync.
- **Manual sync:** `php backend/artisan jobhunt:sync-mail` syncs from the terminal. Add `--account=you@gmail.com` to sync one account.
- **Removing access:** **Disconnect** in Settings revokes JobHunt's access at Google. You can also remove it at https://myaccount.google.com/permissions.
