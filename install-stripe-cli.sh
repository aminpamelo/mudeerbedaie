#!/bin/bash

# Stripe CLI Installation Script
# Supports multiple installation methods for different systems

echo "🔧 Stripe CLI Installation Script"
echo "=================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Detect OS
detect_os() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macOS"
    elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
        echo "Linux"
    else
        echo "Unknown"
    fi
}

# Check if Stripe CLI is already installed
check_existing_installation() {
    if command -v stripe &> /dev/null; then
        STRIPE_VERSION=$(stripe --version)
        print_success "Stripe CLI is already installed: $STRIPE_VERSION"
        return 0
    else
        return 1
    fi
}

# Install using Homebrew (macOS)
install_with_homebrew() {
    print_status "Installing Stripe CLI with Homebrew..."
    
    # Check if Homebrew is installed
    if ! command -v brew &> /dev/null; then
        print_warning "Homebrew is not installed. Installing Homebrew first..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
        
        if [ $? -ne 0 ]; then
            print_error "Failed to install Homebrew"
            return 1
        fi
    fi
    
    # Install Stripe CLI
    brew install stripe/stripe-cli/stripe
    
    if [ $? -eq 0 ]; then
        print_success "Stripe CLI installed successfully via Homebrew!"
        return 0
    else
        print_error "Failed to install Stripe CLI via Homebrew"
        return 1
    fi
}

# Install by direct download (macOS/Linux)
install_direct_download() {
    print_status "Installing Stripe CLI via direct download..."
    
    OS=$(detect_os)
    
    if [ "$OS" = "macOS" ]; then
        DOWNLOAD_URL="https://github.com/stripe/stripe-cli/releases/latest/download/stripe_darwin_amd64.tar.gz"
    elif [ "$OS" = "Linux" ]; then
        DOWNLOAD_URL="https://github.com/stripe/stripe-cli/releases/latest/download/stripe_linux_amd64.tar.gz"
    else
        print_error "Unsupported operating system for direct download"
        return 1
    fi
    
    # Create temporary directory
    TEMP_DIR=$(mktemp -d)
    cd "$TEMP_DIR" || return 1
    
    # Download and extract
    print_status "Downloading from: $DOWNLOAD_URL"
    curl -L "$DOWNLOAD_URL" -o stripe_cli.tar.gz
    
    if [ $? -ne 0 ]; then
        print_error "Failed to download Stripe CLI"
        rm -rf "$TEMP_DIR"
        return 1
    fi
    
    # Extract
    tar -xzf stripe_cli.tar.gz
    
    # Install to /usr/local/bin
    print_status "Installing to /usr/local/bin/stripe..."
    sudo mv stripe /usr/local/bin/
    sudo chmod +x /usr/local/bin/stripe
    
    # Cleanup
    cd - > /dev/null
    rm -rf "$TEMP_DIR"
    
    if command -v stripe &> /dev/null; then
        print_success "Stripe CLI installed successfully via direct download!"
        return 0
    else
        print_error "Installation completed but Stripe CLI is not accessible"
        return 1
    fi
}

# Install using package manager (Linux)
install_package_manager() {
    print_status "Installing Stripe CLI via package manager..."
    
    # Check if we're on a Debian-based system
    if command -v apt &> /dev/null; then
        print_status "Detected Debian/Ubuntu system"
        
        # Add Stripe repository
        curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg > /dev/null
        echo "deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main" | sudo tee -a /etc/apt/sources.list.d/stripe.list
        
        # Update and install
        sudo apt update
        sudo apt install stripe -y
        
        if [ $? -eq 0 ]; then
            print_success "Stripe CLI installed successfully via apt!"
            return 0
        else
            print_error "Failed to install Stripe CLI via apt"
            return 1
        fi
    else
        print_error "Package manager installation not supported for your system"
        return 1
    fi
}

# Verify installation
verify_installation() {
    print_status "Verifying installation..."
    
    if command -v stripe &> /dev/null; then
        STRIPE_VERSION=$(stripe --version)
        print_success "Stripe CLI is working: $STRIPE_VERSION"
        
        echo ""
        echo "🎉 Installation completed successfully!"
        echo ""
        echo "Next steps:"
        echo "1. Run: stripe login"
        echo "2. Start webhook forwarding: ./setup-stripe-webhook.sh"
        echo "3. Or use the setup script: ./setup-stripe-webhook.sh"
        
        return 0
    else
        print_error "Stripe CLI installation verification failed"
        return 1
    fi
}

# Show installation options
show_menu() {
    echo ""
    echo "Please choose an installation method:"
    echo ""
    echo "1. Homebrew (macOS - Recommended)"
    echo "2. Direct Download (macOS/Linux)"
    echo "3. Package Manager (Linux only)"
    echo "4. Manual Instructions"
    echo "5. Exit"
    echo ""
    
    read -p "Enter your choice (1-5): " -n 1 -r
    echo ""
    
    case $REPLY in
        1)
            if [ "$(detect_os)" = "macOS" ]; then
                install_with_homebrew
            else
                print_error "Homebrew is primarily for macOS systems"
                return 1
            fi
            ;;
        2)
            install_direct_download
            ;;
        3)
            if [ "$(detect_os)" = "Linux" ]; then
                install_package_manager
            else
                print_error "Package manager installation is for Linux systems"
                return 1
            fi
            ;;
        4)
            show_manual_instructions
            return 0
            ;;
        5)
            print_success "Installation cancelled"
            return 0
            ;;
        *)
            print_error "Invalid choice"
            return 1
            ;;
    esac
}

# Show manual installation instructions
show_manual_instructions() {
    echo ""
    print_status "Manual Installation Instructions"
    echo ""
    
    echo "🍺 macOS (Homebrew):"
    echo "  brew install stripe/stripe-cli/stripe"
    echo ""
    
    echo "📦 Linux (Package Manager):"
    echo "  # Debian/Ubuntu"
    echo "  curl -s https://packages.stripe.dev/api/security/keypair/stripe-cli-gpg/public | gpg --dearmor | sudo tee /usr/share/keyrings/stripe.gpg"
    echo "  echo \"deb [signed-by=/usr/share/keyrings/stripe.gpg] https://packages.stripe.dev/stripe-cli-debian-local stable main\" | sudo tee -a /etc/apt/sources.list.d/stripe.list"
    echo "  sudo apt update && sudo apt install stripe"
    echo ""
    
    echo "🔗 Direct Download:"
    echo "  Visit: https://github.com/stripe/stripe-cli/releases/latest"
    echo "  Download the appropriate version for your system"
    echo "  Extract and move to /usr/local/bin/"
    echo ""
    
    echo "📖 Official Documentation:"
    echo "  https://stripe.com/docs/stripe-cli#install"
}

# Main execution
main() {
    echo ""
    
    # Check if already installed
    if check_existing_installation; then
        echo ""
        echo "What would you like to do?"
        echo "1. Continue with current installation"
        echo "2. Reinstall"
        echo "3. Exit"
        echo ""
        
        read -p "Enter your choice (1-3): " -n 1 -r
        echo ""
        
        case $REPLY in
            1)
                print_success "Using existing installation"
                verify_installation
                return 0
                ;;
            2)
                print_status "Proceeding with reinstallation..."
                ;;
            3)
                print_success "Goodbye!"
                return 0
                ;;
            *)
                print_error "Invalid choice"
                return 1
                ;;
        esac
    fi
    
    # Show installation menu
    if show_menu; then
        verify_installation
    else
        print_error "Installation failed"
        return 1
    fi
}

# Handle command line arguments
case "$1" in
    "check")
        check_existing_installation
        ;;
    "homebrew")
        install_with_homebrew && verify_installation
        ;;
    "download")
        install_direct_download && verify_installation
        ;;
    "package")
        install_package_manager && verify_installation
        ;;
    "manual")
        show_manual_instructions
        ;;
    *)
        main
        ;;
esac