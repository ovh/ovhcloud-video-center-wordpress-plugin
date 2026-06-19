# OVHcloud Video Center WordPress Plugin - Makefile
# 
# Usage:
#   make build    - Build the Gutenberg block
#   make zip      - Create a distribution zip (excludes dev files)
#   make clean    - Clean build artifacts
#   make clean-build - Clean all build artifacts including dist and node_modules

PLUGIN_NAME := ovhcloud-video-center
VERSION     := 0.0.1
BUILD_DIR   := dist
ZIP_FILE    := $(PLUGIN_NAME)-$(VERSION).zip
BLOCK_DIR   := blocks/ovhcloud-video-center

.PHONY: help build zip clean clean-build install

help:
	@echo "OVHcloud Video Center WordPress Plugin - Build"
	@echo ""
	@echo "Available targets:"
	@echo "  make install      - Install npm dependencies for block"
	@echo "  make build        - Build Gutenberg block (npm run build)"
	@echo "  make zip          - Create distribution zip file"
	@echo "  make clean        - Remove node_modules only (keeps build files)"
	@echo "  make clean-build  - Remove all build artifacts, dist and node_modules"

# Install npm dependencies
install:
	@echo "Installing npm dependencies..."
	cd $(BLOCK_DIR) && npm install

# Build the Gutenberg block
build: install
	@echo "Building Gutenberg block..."
	cd $(BLOCK_DIR) && npm run build
	@echo "✓ Build complete"

# Create distribution zip
zip: build
	@echo "Creating distribution package..."
	@mkdir -p $(BUILD_DIR)
	@rm -f $(BUILD_DIR)/$(ZIP_FILE)
	@rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	@rsync -a --exclude='.git' --exclude='.github' --exclude='.gitignore' --exclude='node_modules' \
		--exclude='$(BLOCK_DIR)/node_modules' --exclude='$(BLOCK_DIR)/src' \
		--exclude='$(BLOCK_DIR)/package-lock.json' --exclude='dist' \
		--exclude='.DS_Store' --exclude='Makefile' --exclude='phpunit.xml' \
		--exclude='tests' . $(BUILD_DIR)/$(PLUGIN_NAME)/
	@cd $(BUILD_DIR) && zip -r $(ZIP_FILE) $(PLUGIN_NAME)/
	@rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)
	@echo "✓ Created $(BUILD_DIR)/$(ZIP_FILE)"
	@ls -lh $(BUILD_DIR)/$(ZIP_FILE)

# Remove only node_modules (temporary install artifacts — keeps compiled build files intact)
clean:
	@echo "Removing node_modules..."
	@rm -rf $(BLOCK_DIR)/node_modules
	@echo "✓ node_modules removed"

# Remove all build artifacts including compiled block files and dist zip
clean-build:
	@echo "Removing all build artifacts..."
	@rm -rf $(BUILD_DIR)
	@rm -rf $(BLOCK_DIR)/build
	@rm -rf $(BLOCK_DIR)/node_modules
	@echo "✓ Full clean complete"
