# 📮 BugRadar Postman Collection - Index

Welcome to the BugRadar API Postman collection! This folder contains everything you need to test the API.

---

## 🚀 Quick Start (3 Steps)

1. **Import** → Open Postman → Import → Select all 3 JSON files
2. **Select** → Choose "BugRadar - Local" environment
3. **Test** → Run "Dev Login" → Run "Get Current User"

**Done!** You're ready to test all 26 endpoints.

---

## 📁 Files in This Folder

### 🔧 Postman Files (Import These)
| File | Description | Import |
|------|-------------|--------|
| `BugRadar-API.postman_collection.json` | Complete API collection (26 requests) | ✅ Required |
| `BugRadar-Local.postman_environment.json` | Local development environment | ✅ Required |
| `BugRadar-Production.postman_environment.json` | Production environment | ✅ Required |

### 📚 Documentation Files (Read These)
| File | Description | When to Read |
|------|-------------|--------------|
| `IMPORT_GUIDE.md` | Step-by-step import instructions | 👉 **Start here** |
| `README.md` | Complete usage guide | After import |
| `COLLECTION_SUMMARY.md` | Quick reference of all endpoints | Reference |
| `INDEX.md` | This file | Navigation |

---

## 📖 Reading Order

### For First-Time Users:
```
1. IMPORT_GUIDE.md    ← Start here (5 min)
2. README.md          ← Understand the collection (10 min)
3. COLLECTION_SUMMARY.md ← Quick reference (5 min)
```

### For Quick Testing:
```
1. Import 3 JSON files
2. Select environment
3. Run "Dev Login"
4. Start testing!
```

---

## 🎯 What's Inside the Collection

### 26 API Requests Organized in 6 Folders:

1. **Authentication** (5 requests)
   - OAuth flows (Google, GitHub)
   - Dev login for testing
   - User info and logout

2. **Integrations** (6 requests)
   - Connect platforms (GitHub, GitLab, Bitbucket)
   - List, sync, and disconnect

3. **Pull Requests** (4 requests)
   - List with filters
   - Get details
   - View reviewed PRs

4. **Issues** (5 requests)
   - List with filters
   - Get details
   - Filter by type (bugs, tasks)

5. **Reviews** (4 requests)
   - List review history
   - Get statistics
   - Filter by date

6. **Dashboard** (2 requests)
   - Overview statistics
   - Recent activity

---

## 🌍 Environments

### Local Development
```
Base URL: http://localhost:8006
Use for: Testing on your machine
```

### Production
```
Base URL: https://your-domain.com
Use for: Testing deployed API
```

---

## 💡 Common Use Cases

### Use Case 1: First Time Setup
```
Goal: Get started and test basic functionality
Steps:
  1. Import collection
  2. Run "Dev Login"
  3. Run "Get Current User"
  4. Run "Get Dashboard Statistics"
Time: 5 minutes
```

### Use Case 2: Test Integration Flow
```
Goal: Connect a platform and sync data
Steps:
  1. Run "Connect GitHub" (in browser)
  2. Run "List All Integrations"
  3. Run "Sync Integration"
  4. Run "List Pull Requests"
Time: 10 minutes
```

### Use Case 3: Explore All Data
```
Goal: View all available data
Steps:
  1. Run "List Pull Requests"
  2. Run "List Issues"
  3. Run "List Reviews"
  4. Run "Get Dashboard Statistics"
Time: 5 minutes
```

### Use Case 4: Test Filtering
```
Goal: Test query parameters and filters
Steps:
  1. Run "List Pull Requests - Filtered"
  2. Run "List Issues - High Priority Bugs"
  3. Run "List Reviews - This Month"
Time: 5 minutes
```

---

## 🔐 Authentication

All requests (except OAuth and Dev Login) require authentication:

```
Authorization: Bearer {{auth_token}}
```

**How to get token:**
- **Local**: Run "Dev Login" (auto-saves token)
- **Production**: Complete OAuth in browser, copy token

---

## 📊 Collection Stats

- **Total Requests**: 26
- **Total Folders**: 6
- **Environments**: 2
- **Coverage**: 100% of API endpoints
- **Auto-authentication**: ✅ Yes
- **Auto-save token**: ✅ Yes
- **Query parameters**: ✅ Included
- **Descriptions**: ✅ All requests

---

## 🎨 Features

### ✅ Ready to Use
- Pre-configured requests
- Example query parameters
- Auto-authentication
- Environment variables

### ✅ Easy Testing
- One-click requests
- Auto-save tokens
- Clear descriptions
- Example responses

### ✅ Well Documented
- Request descriptions
- Usage examples
- Response formats
- Error handling

---

## 🐛 Troubleshooting

### Can't Import?
→ See `IMPORT_GUIDE.md`

### Requests Failing?
→ Check environment is selected
→ Verify `base_url` is correct
→ Ensure server is running

### Token Not Working?
→ Run "Dev Login" again
→ Check token in environment
→ Verify token hasn't expired

### Need More Help?
→ Read `README.md`
→ Check `../API_DOCUMENTATION.md`
→ Review Laravel logs

---

## 📚 Additional Resources

### In This Folder:
- `IMPORT_GUIDE.md` - Import instructions
- `README.md` - Complete guide
- `COLLECTION_SUMMARY.md` - Quick reference

### In Parent Folder:
- `../API_DOCUMENTATION.md` - Full API docs
- `../QUICK_START.md` - Quick start guide
- `../DEPLOYMENT_GUIDE.md` - Deployment instructions
- `../BACKEND_COMPLETE.md` - Feature overview

---

## 🎉 Ready to Start!

1. **Import** the 3 JSON files into Postman
2. **Read** IMPORT_GUIDE.md for step-by-step instructions
3. **Test** your first request in under 5 minutes!

---

## 📞 Support

If you encounter issues:
1. Check the documentation files
2. Review Laravel logs: `storage/logs/laravel.log`
3. Verify environment variables
4. Ensure queue workers are running

---

**Happy Testing! 🚀**
