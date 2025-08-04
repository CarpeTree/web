#!/bin/bash
# Setup SSH key for passwordless deployment to Hostinger

echo "🔑 Setting up SSH key for passwordless deployment..."

# Your SSH key (you provided this earlier)
SSH_KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIH4zy5MAGz7OK2pqoyY4Gs40xSxMVIBXn3raJ0FaoC4K carpe-tree-hostinger"

# Create SSH directory if it doesn't exist
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Create the SSH key file
echo "📝 Creating SSH key file..."
echo "$SSH_KEY" > ~/.ssh/carpe-tree-hostinger.pub

# You'll need to create the private key file manually or provide it
echo "⚠️  You need to create the private key file at: ~/.ssh/carpe-tree-hostinger"
echo "   (This is the private part of your SSH key pair)"

# Set correct permissions
chmod 600 ~/.ssh/carpe-tree-hostinger* 2>/dev/null || true

echo "🚀 Next steps:"
echo "1. Add your private key to ~/.ssh/carpe-tree-hostinger"
echo "2. Add the public key to your Hostinger SSH settings"
echo "3. Test with: ssh -i ~/.ssh/carpe-tree-hostinger -p 65002 u230128646@46.202.182.11"

echo ""
echo "📋 Your public key to add to Hostinger:"
echo "$SSH_KEY"