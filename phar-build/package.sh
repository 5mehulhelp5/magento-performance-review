#!/bin/bash

# Package the standalone performance review tool
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PACKAGE_NAME="magento-performance-review"
PACKAGE_DIR="${SCRIPT_DIR}/${PACKAGE_NAME}"

echo "Creating standalone package..."

# Remove existing package
rm -rf "${PACKAGE_DIR}" "${PACKAGE_DIR}.tar.gz"

# Create package directory
mkdir -p "${PACKAGE_DIR}"

# Copy necessary files
cp -r "${SCRIPT_DIR}/src" "${PACKAGE_DIR}/"
cp -r "${SCRIPT_DIR}/bin" "${PACKAGE_DIR}/"
cp -r "${SCRIPT_DIR}/vendor" "${PACKAGE_DIR}/"
cp "${SCRIPT_DIR}/composer.json" "${PACKAGE_DIR}/"
cp "${SCRIPT_DIR}/composer.lock" "${PACKAGE_DIR}/"
cp "${SCRIPT_DIR}/README.md" "${PACKAGE_DIR}/"

# Create a simple wrapper script
cat > "${PACKAGE_DIR}/magento-performance-review" << 'EOF'
#!/bin/bash
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
php "${SCRIPT_DIR}/bin/magento-performance-review" "$@"
EOF

chmod +x "${PACKAGE_DIR}/magento-performance-review"

# Create tarball
tar -czf "${PACKAGE_DIR}.tar.gz" -C "${SCRIPT_DIR}" "${PACKAGE_NAME}"

# Clean up
rm -rf "${PACKAGE_DIR}"

echo "Package created: ${PACKAGE_DIR}.tar.gz"
echo ""
echo "To use:"
echo "1. Extract: tar -xzf ${PACKAGE_NAME}.tar.gz"
echo "2. Run: ./${PACKAGE_NAME}/magento-performance-review [options]"
echo ""
echo "Example:"
echo "  ./${PACKAGE_NAME}/magento-performance-review --magento-root=/path/to/magento"