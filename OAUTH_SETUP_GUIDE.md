# OAuth Setup Guide - Google & GitHub

This guide will walk you through setting up OAuth credentials for Google and GitHub authentication in BugRadar.

---

## 🔵 Google OAuth Setup

### Step 1: Go to Google Cloud Console

1. Visit [Google Cloud Console](https://console.cloud.google.com/)
2. Sign in with your Google account

### Step 2: Create a New Project (or Select Existing)

1. Click on the project dropdown at the top of the page
2. Click **"New Project"**
3. Enter project name: `BugRadar` (or any name you prefer)
4. Click **"Create"**
5. Wait for the project to be created and select it

### Step 3: Enable Google+ API

1. In the left sidebar, go to **"APIs & Services"** → **"Library"**
2. Search for **"Google+ API"** or **"Google Identity"**
3. Click on **"Google+ API"**
4. Click **"Enable"**

### Step 4: Configure OAuth Consent Screen

1. Go to **"APIs & Services"** → **"OAuth consent screen"**
2. Select **"External"** (unless you have a Google Workspace)
3. Click **"Create"**

**Fill in the required fields:**
- **App name**: `BugRadar`
- **User support email**: Your email
- **Developer contact email**: Your email
- Click **"Save and Continue"**

**Scopes (Step 2):**
- Click **"Add or Remove Scopes"**
- Select:
  - `openid`
  - `profile`
  - `email`
- Click **"Update"** → **"Save and Continue"**

**Test Users (Step 3):**
- Add your email address as a test user
- Click **"Save and Continue"**

**Summary (Step 4):**
- Review and click **"Back to Dashboard"**

### Step 5: Create OAuth 2.0 Credentials

1. Go to **"APIs & Services"** → **"Credentials"**
2. Click **"+ Create Credentials"** → **"OAuth client ID"**
3. Select **"Web application"**

**Configure the OAuth client:**
- **Name**: `BugRadar Web Client`
- **Authorized JavaScript origins**: 
  - `http://localhost:8006`
  - `http://127.0.0.1:8006`
- **Authorized redirect URIs**:
  - `http://localhost:8006/api/auth/google/callback`
  - `http://127.0.0.1:8006/api/auth/google/callback`

4. Click **"Create"**

### Step 6: Copy Your Credentials

A popup will show your credentials:
- **Client ID**: Something like `123456789-abcdefg.apps.googleusercontent.com`
- **Client Secret**: Something like `GOCSPX-abc123def456`

**Copy these values!** You'll need them for your `.env` file.

### Step 7: Update Your .env File

```env
GOOGLE_CLIENT_ID=your_client_id_here
GOOGLE_CLIENT_SECRET=your_client_secret_here
GOOGLE_REDIRECT_URI=http://localhost:8006/api/auth/google/callback
```

---

## 🐙 GitHub OAuth Setup

### Step 1: Go to GitHub Developer Settings

1. Visit [GitHub Developer Settings](https://github.com/settings/developers)
2. Or navigate: GitHub → Settings → Developer settings → OAuth Apps

### Step 2: Create a New OAuth App

1. Click **"New OAuth App"** button
2. Fill in the application details:

**Application Details:**
- **Application name**: `BugRadar`
- **Homepage URL**: `http://localhost:8006`
- **Application description**: `Developer issue tracking and DevOps companion` (optional)
- **Authorization callback URL**: `http://localhost:8006/api/auth/github/callback`

3. Click **"Register application"**

### Step 3: Generate Client Secret

1. After creating the app, you'll see your **Client ID**
2. Click **"Generate a new client secret"**
3. Confirm your password if prompted
4. **Copy the client secret immediately** - you won't be able to see it again!

### Step 4: Copy Your Credentials

You should now have:
- **Client ID**: Something like `Iv1.a1b2c3d4e5f6g7h8`
- **Client Secret**: Something like `abc123def456ghi789jkl012mno345pqr678stu`

### Step 5: Update Your .env File

```env
GITHUB_CLIENT_ID=your_client_id_here
GITHUB_CLIENT_SECRET=your_client_secret_here
GITHUB_REDIRECT_URI=http://localhost:8006/api/auth/github/callback
```

---

## 🔗 GitHub Integration OAuth (Optional - For Data Sync)

If you want to use a separate OAuth app for GitHub integration (recommended for production):

### Create Another GitHub OAuth App

1. Go back to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **"New OAuth App"**
3. Fill in:
   - **Application name**: `BugRadar Integration`
   - **Homepage URL**: `http://localhost:8006`
   - **Authorization callback URL**: `http://localhost:8006/api/integrations/github/callback`
4. Click **"Register application"**
5. Generate and copy the client secret

### Update .env for Integration

```env
GITHUB_INTEGRATION_CLIENT_ID=your_integration_client_id_here
GITHUB_INTEGRATION_CLIENT_SECRET=your_integration_client_secret_here
GITHUB_INTEGRATION_REDIRECT_URI=http://localhost:8006/api/integrations/github/callback
```

**Note:** For development, you can use the same credentials for both authentication and integration:

```env
GITHUB_INTEGRATION_CLIENT_ID=${GITHUB_CLIENT_ID}
GITHUB_INTEGRATION_CLIENT_SECRET=${GITHUB_CLIENT_SECRET}
```

---

## 📝 Complete .env Example

After setting up both Google and GitHub OAuth, your `.env` file should look like this:

```env
APP_NAME=BugRadar
APP_ENV=local
APP_KEY=base64:your_generated_key_here
APP_DEBUG=true
APP_URL=http://localhost:8006

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bugradar
DB_USERNAME=root
DB_PASSWORD=your_password

# Or use SQLite for development
# DB_CONNECTION=sqlite

# Queue
QUEUE_CONNECTION=database

# OAuth Providers for Authentication
GOOGLE_CLIENT_ID=123456789-abcdefg.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-abc123def456
GOOGLE_REDIRECT_URI=http://localhost:8006/api/auth/google/callback

GITHUB_CLIENT_ID=Iv1.a1b2c3d4e5f6g7h8
GITHUB_CLIENT_SECRET=abc123def456ghi789jkl012mno345pqr678stu
GITHUB_REDIRECT_URI=http://localhost:8006/api/auth/github/callback

# OAuth Providers for Integrations (can be same as above)
GITHUB_INTEGRATION_CLIENT_ID=${GITHUB_CLIENT_ID}
GITHUB_INTEGRATION_CLIENT_SECRET=${GITHUB_CLIENT_SECRET}
GITHUB_INTEGRATION_REDIRECT_URI=http://localhost:8006/api/integrations/github/callback
```

---

## ✅ Testing Your OAuth Setup

### Test Google OAuth

1. Start your Laravel server:
   ```bash
   php artisan serve
   ```

2. Visit in your browser:
   ```
   http://localhost:8006/api/auth/google
   ```

3. You should be redirected to Google's login page
4. After logging in, you should be redirected back to your app

### Test GitHub OAuth

1. Visit in your browser:
   ```
   http://localhost:8006/api/auth/github
   ```

2. You should be redirected to GitHub's authorization page
3. Click "Authorize"
4. You should be redirected back to your app

---

## 🚨 Common Issues & Solutions

### Issue: "Redirect URI mismatch" (Google)

**Solution:** 
- Make sure the redirect URI in Google Cloud Console exactly matches your `.env` file
- Check for trailing slashes
- Ensure you're using the correct port (8000)

### Issue: "The redirect_uri MUST match the registered callback URL" (GitHub)

**Solution:**
- Verify the callback URL in GitHub OAuth App settings matches your `.env`
- No trailing slashes
- Use exact same protocol (http vs https)

### Issue: "Client authentication failed" (Google)

**Solution:**
- Double-check your Client ID and Client Secret
- Make sure there are no extra spaces when copying
- Regenerate the client secret if needed

### Issue: OAuth works in browser but not in mobile app

**Solution:**
- Mobile app uses deep links, which are configured separately
- The backend OAuth should work first before testing mobile
- Make sure your Laravel backend is accessible from the mobile device/emulator

---

## 🔒 Security Notes

### For Development:
- Using `http://localhost` is fine
- Test users can be added in Google OAuth consent screen

### For Production:
- **Always use HTTPS** in production
- Update redirect URIs to your production domain
- Remove test users and publish your OAuth consent screen
- Use environment variables for secrets
- Never commit `.env` file to version control
- Consider using separate OAuth apps for development and production

---

## 📱 Mobile App Configuration

After setting up backend OAuth, the mobile app will use these endpoints:

**For Android Emulator:**
- API Base URL: `http://10.0.2.2:8000/api`

**For iOS Simulator:**
- API Base URL: `http://localhost:8006/api`

**For Physical Device:**
- API Base URL: `http://YOUR_MACHINE_IP:8000/api`
- Example: `http://192.168.1.100:8000/api`

Update in `bugradar_mobile/lib/config/app_config.dart`:

```dart
static const String apiBaseUrl = 'http://10.0.2.2:8000/api'; // Android
// or
static const String apiBaseUrl = 'http://192.168.1.100:8000/api'; // Physical device
```

---

## 🎯 Next Steps

After setting up OAuth:

1. ✅ Copy credentials to `.env` file
2. ✅ Run `php artisan config:clear`
3. ✅ Test OAuth in browser
4. ✅ Start Laravel server: `php artisan serve`
5. ✅ Run mobile app: `cd bugradar_mobile && flutter run`
6. ✅ Test login flow in mobile app

---

## 📚 Additional Resources

- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [GitHub OAuth Documentation](https://docs.github.com/en/developers/apps/building-oauth-apps)
- [Laravel Socialite Documentation](https://laravel.com/docs/socialite)

---

## 💡 Tips

1. **Save your credentials securely** - Store them in a password manager
2. **Use different OAuth apps** for development and production
3. **Test with multiple accounts** to ensure it works for all users
4. **Monitor OAuth usage** in Google Cloud Console and GitHub settings
5. **Set up proper scopes** - Only request what you need

---

Need help? Check the troubleshooting section or refer to the official documentation links above.
