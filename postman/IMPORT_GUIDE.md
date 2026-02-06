# How to Import BugRadar API Collection into Postman

## 📥 Quick Import (3 Steps)

### Step 1: Open Postman
- Launch Postman application
- Or use Postman web version at https://web.postman.co

### Step 2: Import Files
1. Click the **Import** button (top left)
2. Click **files** or drag and drop
3. Select these 3 files:
   - `BugRadar-API.postman_collection.json`
   - `BugRadar-Local.postman_environment.json`
   - `BugRadar-Production.postman_environment.json`
4. Click **Import**

### Step 3: Select Environment
- Click the environment dropdown (top right)
- Select **BugRadar - Local** for local testing
- Or select **BugRadar - Production** for production

---

## 🎯 First Request

### Test the API (Local Development)

1. **Select Environment**: Choose **BugRadar - Local**

2. **Get Auth Token**:
   - Expand **Authentication** folder
   - Click **Dev Login (Local Only)**
   - Click **Send**
   - Token will be automatically saved ✅

3. **Test Authentication**:
   - Click **Get Current User**
   - Click **Send**
   - You should see your user data ✅

4. **View Dashboard**:
   - Expand **Dashboard** folder
   - Click **Get Dashboard Statistics**
   - Click **Send**
   - You should see stats ✅

---

## 🌐 For Production Testing

### Update Production Environment

1. Click environment dropdown
2. Select **BugRadar - Production**
3. Click the eye icon (👁️) next to environment name
4. Click **Edit**
5. Update `base_url` to your production URL:
   ```
   https://your-domain.com
   ```
6. Click **Save**

### Get Production Token

Since Dev Login doesn't work in production, use OAuth:

1. Go to **Authentication** → **Google OAuth - Redirect**
2. Copy the request URL
3. Open URL in browser
4. Complete OAuth login
5. Copy the token from response
6. In Postman:
   - Click environment dropdown
   - Click eye icon (👁️)
   - Click **Edit**
   - Paste token into `auth_token` value
   - Click **Save**

---

## 📱 Alternative: Import via URL

If you have the collection hosted online:

1. Click **Import** in Postman
2. Select **Link** tab
3. Paste the URL to the collection JSON
4. Click **Continue**
5. Click **Import**

---

## 🔧 Verify Import

After importing, you should see:

### Collections (Left Sidebar)
- ✅ **BugRadar API** collection with 6 folders
  - Authentication (5 requests)
  - Integrations (6 requests)
  - Pull Requests (4 requests)
  - Issues (5 requests)
  - Reviews (4 requests)
  - Dashboard (2 requests)

### Environments (Top Right Dropdown)
- ✅ **BugRadar - Local**
- ✅ **BugRadar - Production**

---

## 🎨 Collection Features

### Auto-Authentication
All requests automatically use the `auth_token` from environment:
```
Authorization: Bearer {{auth_token}}
```

### Auto-Save Token
The Dev Login request automatically saves the token to environment.

### Query Parameters
All list endpoints have example query parameters (disabled by default):
- Enable them to test filtering
- Modify values to test different scenarios

### Request Descriptions
Each request includes:
- Description of what it does
- Expected response format
- Usage examples

---

## 💡 Pro Tips

### 1. Use Collection Runner
Test all endpoints at once:
- Right-click collection
- Select **Run collection**
- Choose environment
- Click **Run BugRadar API**

### 2. Save Responses as Examples
After getting a successful response:
- Click **Save Response**
- Select **Save as example**
- Helps document expected responses

### 3. Create Test Scripts
Add tests to verify responses:
```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has data", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('data');
});
```

### 4. Use Variables
Update environment variables for different test scenarios:
- `integration_id` - Test different integrations
- `pr_id` - Test different pull requests
- `issue_id` - Test different issues

---

## 🐛 Troubleshooting Import

### Collection Not Showing
- Refresh Postman
- Check Collections tab (left sidebar)
- Try importing again

### Environment Not Available
- Check environment dropdown (top right)
- Click **Manage Environments**
- Verify environments are listed

### Requests Failing
- Verify environment is selected
- Check `base_url` is correct
- Ensure Laravel server is running
- Get a valid auth token

### Token Not Saving
- Check if Dev Login request completed successfully
- Manually set token in environment
- Verify test script is present in Dev Login request

---

## 📚 Next Steps

After importing:

1. ✅ **Read README.md** - Understand collection structure
2. ✅ **Test Authentication** - Get a valid token
3. ✅ **Connect Integration** - Link GitHub/GitLab/Bitbucket
4. ✅ **Sync Data** - Trigger a sync job
5. ✅ **Explore Endpoints** - Test all API features

---

## 🎉 You're Ready!

The collection is now imported and ready to use. Start testing the BugRadar API!

For detailed API documentation, see: `../API_DOCUMENTATION.md`
