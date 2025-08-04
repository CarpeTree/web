#!/bin/bash
# Deploy to Hostinger via SSH
# Make sure your SSH key is added to your Hostinger account

echo "🚀 Deploying Carpe Tree website to Hostinger..."

# SSH connection details (you'll need to update these)
HOST="your-server.hostinger.com"  # Replace with your actual server
USER="your-username"              # Replace with your username
REMOTE_PATH="/public_html"        # Or wherever your website files go

# Files to upload
FILES_TO_UPLOAD=(
    "quote.html"
    "debug-simple.html"
    "index.html"
)

echo "📡 Connecting to Hostinger server..."

# Upload each file
for file in "${FILES_TO_UPLOAD[@]}"; do
    if [ -f "$file" ]; then
        echo "📤 Uploading $file..."
        scp -i ~/.ssh/carpe-tree-hostinger "$file" "$USER@$HOST:$REMOTE_PATH/"
        if [ $? -eq 0 ]; then
            echo "✅ $file uploaded successfully"
        else
            echo "❌ Failed to upload $file"
        fi
    else
        echo "⚠️  $file not found locally"
    fi
done

echo "🔍 Testing deployment..."
echo "Try these URLs:"
echo "  https://carpetree.com/debug-simple.html"
echo "  https://carpetree.com/quote.html"

echo "✅ Deployment complete!"