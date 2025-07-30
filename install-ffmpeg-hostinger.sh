#!/bin/bash
# FFmpeg installer for Hostinger shared hosting
# Run this script via SSH on your Hostinger server

echo "🎬 Installing FFmpeg for Hostinger shared hosting..."

# Create bin directory
mkdir -p ~/bin
cd ~/bin

# Download static FFmpeg build
echo "📥 Downloading FFmpeg static build..."
curl -L -o ffmpeg.tar.xz https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz

if [ ! -f ffmpeg.tar.xz ]; then
    echo "❌ Download failed. Check your internet connection."
    exit 1
fi

# Extract
echo "📦 Extracting..."
tar -xf ffmpeg.tar.xz

# Move binary
mv ffmpeg-*-static/ffmpeg .

# Cleanup
rm -rf ffmpeg-*-static ffmpeg.tar.xz

# Make executable
chmod +x ffmpeg

# Add to PATH
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bash_profile
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bashrc

# Test
if [ -x ~/bin/ffmpeg ]; then
    echo "✅ FFmpeg installed successfully!"
    echo "📍 Location: ~/bin/ffmpeg"
    echo "🔧 Version: $(~/bin/ffmpeg -version | head -1)"
    echo ""
    echo "📝 Next steps:"
    echo "1. Log out and back in (or run: source ~/.bash_profile)"
    echo "2. Test with: which ffmpeg"
    echo "3. Your PHP scripts will now find FFmpeg automatically"
else
    echo "❌ Installation failed"
    exit 1
fi