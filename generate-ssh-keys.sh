#!/bin/bash
# Generate new SSH key pair for Hostinger deployment

echo "🔑 Generating new SSH key pair for Hostinger..."

# Generate new SSH key
ssh-keygen -t ed25519 -f ~/.ssh/carpe-tree-hostinger -N "" -C "carpe-tree-hostinger"

echo ""
echo "✅ SSH key pair generated!"
echo "📁 Private key: ~/.ssh/carpe-tree-hostinger"
echo "📁 Public key: ~/.ssh/carpe-tree-hostinger.pub"

echo ""
echo "📋 Your NEW public key to add to Hostinger:"
echo "================================================"
cat ~/.ssh/carpe-tree-hostinger.pub
echo "================================================"

echo ""
echo "🚀 Next steps:"
echo "1. Copy the public key above"
echo "2. Go to Hostinger Control Panel → Advanced → SSH Access"
echo "3. Click 'Add SSH Key' and paste the public key"
echo "4. Test connection: ssh -i ~/.ssh/carpe-tree-hostinger -p 65002 u230128646@46.202.182.11"
echo "5. Run ./instant-deploy.sh for passwordless deployment!"