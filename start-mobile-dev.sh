#!/bin/bash

# BugRadar Mobile Development Server
# This script starts the Laravel backend for mobile app development

echo "🚀 Starting BugRadar Backend for Mobile Development"
echo ""

# Get machine IP
IP=$(ifconfig | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -1)

echo "📱 Your machine's IP: $IP"
echo "🌐 Backend will be accessible at: http://$IP:8006"
echo ""
echo "⚠️  Make sure to update OAuth redirect URIs:"
echo "   Google: http://$IP:8006/api/auth/google/callback"
echo "   GitHub: http://$IP:8006/api/auth/github/callback"
echo ""
echo "📲 Update Flutter app config if IP changed:"
echo "   bugradar_mobile/lib/config/app_config.dart"
echo "   Change _host to: '$IP'"
echo ""
echo "Starting server..."
echo ""

# Start Laravel server on all interfaces
php artisan serve --host=0.0.0.0 --port=8006
