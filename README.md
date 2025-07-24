# 🤖 AA Meetings Calendar - Fully Automated Static Site Generator

A **completely automated** PHP-based calendar site that generates Seattle AA meeting schedules with zero manual intervention. Set it up once, and it maintains itself automatically with weekly updates.

## ✨ **"Set It And Forget It" Automation**

### 🔄 **Fully Automated Workflow**
- **Weekly Builds**: Every Sunday at 6 AM UTC (Saturday evening US time)
- **Zero Manual Work**: Automatically fetches data, builds site, and deploys
- **Smart Error Handling**: Creates GitHub issues for any failures with troubleshooting
- **Manual Override**: Can be triggered manually when needed
- **Status Monitoring**: Built-in health checks and build validation

### 📅 **Calendar-Based Organization**
- **Monthly Calendar View**: Interactive calendar showing current month with meeting counts
- **Individual Day Pages**: Separate pages for each day with detailed meeting information
- **Location Grouping**: Meetings organized by neighborhood/location within each day
- **Smart Navigation**: Previous/next day navigation and breadcrumb links

### 🚀 **Technical Excellence**
- **GitHub Actions Powered**: Robust CI/CD pipeline with comprehensive error handling
- **Static Hosting**: Deploys to GitHub Pages automatically via `gh-pages` branch
- **SEO Optimized**: Complete meta tags, Open Graph, and Twitter cards
- **Mobile Responsive**: Works perfectly on all devices
- **Performance Focused**: Fast loading with efficient multi-page structure

## 🎯 **Quick Setup (5 Minutes)**

### 1. **Repository Setup**
```bash
# Fork or clone this repository
git clone <your-repo-url>
cd php_meetings

# Install dependencies locally (for development only)
composer install
```

### 2. **GitHub Pages Configuration**
1. Go to your repository **Settings** → **Pages**
2. **Source**: Deploy from a branch
3. **Branch**: `gh-pages` (will be created automatically)
4. **Folder**: `/` (root)
5. Click **Save**

### 3. **Enable GitHub Actions**
1. Go to **Actions** tab in your repository
2. Click **"I understand my workflows and want to enable them"**
3. The workflow will run automatically every Sunday at 6 AM UTC

### 4. **Manual First Build (Optional)**
1. Go to **Actions** → **Build and Deploy AA Meetings Site**
2. Click **"Run workflow"** → **"Run workflow"**
3. Wait ~2-3 minutes for completion
4. Your site will be live at `https://[username].github.io/[repository-name]/`

## 🔧 **How The Automation Works**

### **GitHub Actions Workflow** (`.github/workflows/build-and-deploy.yml`)

```yaml
# Runs every Sunday at 6 AM UTC
schedule:
  - cron: '0 6 * * 0'

# Can be triggered manually
workflow_dispatch:

# Also runs on code changes for testing
push:
  branches: [main]
```

### **Build Process Flow**
1. **🚀 Setup**: Checkout code, configure PHP 8.1, cache dependencies
2. **📦 Install**: Run `composer install` with optimizations
3. **🏗️ Build**: Execute `php build.php` to generate calendar pages
4. **✅ Validate**: Check HTML output, meeting counts, file structure
5. **📝 Metadata**: Create build info and status pages
6. **🚀 Deploy**: Push to `gh-pages` branch for GitHub Pages
7. **🔍 Verify**: Confirm deployment success and branch updates

### **Error Handling & Monitoring**
- **Automatic Issue Creation**: Failed builds create GitHub issues with troubleshooting
- **Build Logs**: Comprehensive logging with grouped output sections
- **Health Checks**: Validates HTML structure and meeting data
- **Status Page**: Auto-generated status page at `/status.html`
- **Retry Logic**: Can manually retry failed builds from Actions interface

## 📊 **What Gets Generated**

### **Site Structure**
```
Generated Website:
├── index.html              # Monthly calendar (main page)
├── day-1.html             # Individual day pages
├── day-2.html             # (only for days with meetings)
├── ...
├── day-31.html
├── status.html            # Build status and information
└── build-info.json       # Machine-readable build metadata
```

### **Monthly Calendar Page** (`index.html`)
- Visual calendar grid for current month
- Meeting counts for each day
- Clickable links to day pages
- Quick access list of all days with meetings
- Last updated timestamp

### **Individual Day Pages** (`day-X.html`)
- Meetings grouped by location/neighborhood
- Sorted by time within each location
- Navigation to previous/next days
- Breadcrumb navigation back to calendar
- Mobile-optimized layout

### **Status & Monitoring** (`status.html`)
- Build success/failure status
- Meeting counts and statistics
- Links to build logs and repository
- Next scheduled update time

## 🛠️ **Customization Options**

### **Change Build Schedule**
Edit `.github/workflows/build-and-deploy.yml`:
```yaml
schedule:
  - cron: '0 6 * * 0'  # Sunday 6 AM UTC
  # Change to: '0 12 * * 1' for Monday noon UTC
```

### **Modify Styling**
- **Global styles**: Edit `templates/base.html.twig`
- **Calendar page**: Edit `templates/calendar.html.twig`
- **Day pages**: Edit `templates/day.html.twig`
- **Location sections**: Edit `templates/location_section.html.twig`

### **Update Data Source**
Edit `src/MeetingsFetcher.php` to change:
- API endpoints
- Data processing logic
- Meeting organization methods

## 🔍 **Monitoring & Troubleshooting**

### **Check Build Status**
1. **GitHub Actions**: Go to Actions tab to see build history
2. **Status Page**: Visit `https://[username].github.io/[repo]/status.html`
3. **Build Info**: Check `build-info.json` for metadata

### **Common Issues & Solutions**

#### **Build Failures**
- **Issue Created**: Automatic GitHub issue with troubleshooting steps
- **Check Logs**: Click on failed workflow run for detailed logs
- **Manual Retry**: Use "Re-run failed jobs" button in Actions
- **API Issues**: Usually resolves automatically on next weekly run

#### **Missing Updates**
```bash
# Check if workflow is enabled
Go to Actions → Build and Deploy → Enable workflow

# Manually trigger build
Actions → Build and Deploy → Run workflow
```

#### **GitHub Pages Not Working**
```bash
# Verify Pages settings
Settings → Pages → Source: Deploy from branch → gh-pages
```

### **Development & Testing**

#### **Local Testing**
```bash
# Install dependencies
composer install

# Run build locally (optional - not needed for production)
php build.php

# The build will create output/ directory
# Note: output/ is ignored in git and only used by GitHub Actions
```

#### **Workflow Testing**
```bash
# Test workflow on code changes
git add .
git commit -m "Test workflow"
git push origin main

# Check Actions tab for results
```

## 📈 **Performance & Statistics**

### **Current Performance** (July 2025)
- **Total Meetings**: ~7,362 meetings organized by calendar day
- **Pages Generated**: 32 pages (1 calendar + 31 day pages)
- **Total Size**: ~6MB for complete month
- **Build Time**: ~2-3 minutes including deployment
- **Hosting**: Free on GitHub Pages

### **Scalability**
- **Static Files**: Instant loading, no server processing
- **CDN Delivery**: GitHub Pages includes global CDN
- **Mobile Optimized**: Responsive design for all screen sizes
- **SEO Friendly**: Complete meta tags and semantic HTML

## 🔒 **Security & Reliability**

### **Automated Security**
- **No Secrets Required**: Uses built-in `GITHUB_TOKEN`
- **Sandboxed Builds**: Each build runs in isolated GitHub runner
- **Static Output**: No server-side vulnerabilities
- **HTTPS**: Automatically served over HTTPS via GitHub Pages

### **Reliability Features**
- **Error Recovery**: Automatic retry on transient failures
- **Fallback Handling**: Graceful degradation if API is down
- **Monitoring**: Issue creation for persistent failures
- **Backup**: All code and history preserved in git

## 🚀 **Advanced Configuration**

### **Custom Domain Setup**
1. Add `CNAME` file to repository root:
   ```
   your-domain.com
   ```
2. Configure DNS at your domain provider
3. Enable HTTPS in GitHub Pages settings

### **Build Notifications**
The workflow automatically:
- Creates issues for failures
- Updates existing failure issues
- Includes troubleshooting information
- Provides direct links to build logs

### **API Monitoring**
Built-in checks for:
- CORS proxy availability
- Seattle AA API response
- Meeting data format validation
- Empty data detection

## 🤝 **Contributing**

### **Development Setup**
```bash
git clone <your-fork>
cd php_meetings
composer install

# Make changes to templates or PHP code
# Test locally if needed: php build.php

git add .
git commit -m "Your changes"
git push origin main

# GitHub Actions will test and deploy automatically
```

### **Reporting Issues**
- **Build Failures**: Issues are created automatically
- **Feature Requests**: Use GitHub Issues
- **Bug Reports**: Include build logs and error details

## 📄 **License**

MIT License - Feel free to fork and customize for your own use!

---

## 🎉 **You're Done!**

Your AA meetings site is now **completely automated**:

✅ **Weekly updates** happen automatically  
✅ **Error handling** creates issues with solutions  
✅ **Manual control** available when needed  
✅ **Zero maintenance** required  

**Live Site**: `https://[username].github.io/[repository-name]/`  
**Status Page**: `https://[username].github.io/[repository-name]/status.html`  
**Next Update**: Every Sunday at 6:00 AM UTC

*Set it and forget it - your meetings site maintains itself!* 🤖