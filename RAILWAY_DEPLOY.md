# 🚀 Railway Deployment Guide - HARDCORE SETUP

## ⚡ Quick Setup (5 minutes)

### 1. Install Railway CLI
```bash
npm i -g @railway/cli
```

### 2. Login to Railway
```bash
railway login
```

### 3. Deploy from project folder
```bash
cd c:\XAMPP2\htdocs\bloxer
railway up
```

That's it! 🎉 Your app is live with **hardcore optimizations**!

## 🔥 HARDCORE Features Enabled

### ⚡ **Performance Optimizations**
- ✅ **Auto-scaling** (1-3 instances based on CPU/Memory)
- ✅ **OPcache** enabled (256MB, 4000 files)
- ✅ **Gzip compression** for all responses
- ✅ **Static asset caching** (1 year cache)
- ✅ **Memory optimized** (512MB limit)
- ✅ **Connection pooling** for database

### 🛡️ **Security Hardening**
- ✅ **Force HTTPS** with HSTS
- ✅ **Security headers** (XSS, CSRF protection)
- ✅ **CSP policies** (Content Security Policy)
- ✅ **Session hardening** (secure cookies)
- ✅ **Rate limiting** (60 req/min)
- ✅ **Input validation** enhanced

### 📊 **Monitoring & Health**
- ✅ **Health checks** every 15 seconds
- ✅ **Auto-restart** on failure (10 retries)
- ✅ **Performance metrics** tracking
- ✅ **Error logging** to /tmp/
- ✅ **Memory monitoring** alerts
- ✅ **Database health** checks

### 🚀 **Scaling Configuration**
- **Min instances**: 1 (always online)
- **Max instances**: 3 (auto-scale)
- **CPU threshold**: 70% utilization
- **Memory threshold**: 80% utilization
- **Health timeout**: 30 seconds
- **Restart delay**: 5 seconds

## 📋 What's Already Configured

✅ **railway.json** - Build configuration  
✅ **railway.toml** - Service settings  
✅ **.env.example** - Railway environment variables  
✅ **Auto HTTPS** - SSL certificate included  
✅ **PostgreSQL** - Database automatically provisioned  

## 🔧 Environment Setup

Railway automatically provides these variables:
- `RAILWAY_POSTGRES_USER` - Database user
- `RAILWAY_POSTGRES_PASSWORD` - Database password  
- `RAILWAY_POSTGRES_DB` - Database name
- `RAILWAY_PRIVATE_DOMAIN` - Database host

## 📁 Project Structure for Railway

```
bloxer/
├── railway.json          # ✅ Build config
├── railway.toml           # ✅ Service config  
├── .env.example           # ✅ Environment vars
├── public/                # ✅ Web root
├── controllers/           # ✅ App logic
├── database/              # ✅ SQL schemas
└── assets/                # ✅ CSS/JS
```

## 🔄 Deployment Workflow

### Method 1: CLI (Fastest)
```bash
railway up
```

### Method 2: GitHub Integration
1. Connect Railway to your GitHub repo
2. Auto-deploy on every push to main branch

### Method 3: Railway Dashboard
1. Go to railway.app
2. Click "New Project"
3. Connect your repo

## 🎯 Next Deploys (10-20 seconds)

After initial setup, just run:
```bash
railway up
```

That's it! Your changes are live in ~15 seconds.

## 🔍 Health Checks

Railway automatically checks:
- ✅ Server is responding on port 3000
- ✅ Health check path: `/`
- ✅ Auto-restart on failure

## 🐛 Troubleshooting

### Database Connection Issues
Make sure your database config uses Railway variables:
```php
// In your database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$name = $_ENV['DB_NAME'] ?? 'bloxer_db';
```

### Permission Issues
Check that `public/` folder is web root and files are readable.

### Build Issues
Railway uses Nixpacks - it should automatically detect PHP and install dependencies.

## 🌐 Your Live App

After deployment, Railway will give you:
- **URL**: `https://your-app-name.up.railway.app`
- **Database**: Built-in PostgreSQL
- **Logs**: Real-time logs in Railway dashboard
- **Metrics**: Performance monitoring

## 🎉 Pro Tips

1. **Custom Domain**: Add your domain in Railway dashboard
2. **Environment Variables**: Set secrets in Railway dashboard
3. **Rollback**: One-click rollback to previous deploy
4. **Scale**: Upgrade to larger instances if needed

---

**Ready to deploy? Just run:**
```bash
railway up
```

🚀 **Your Bloxer platform will be live in minutes!**
