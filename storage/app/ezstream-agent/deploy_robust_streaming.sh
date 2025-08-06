#!/bin/bash
"""
Deployment script for robust streaming system
Deploys and configures all components
"""

set -e  # Exit on any error

echo "🚀 Deploying Robust Streaming System"
echo "===================================="

# Configuration
AGENT_DIR="/opt/ezstream-agent"
SRS_CONFIG_DIR="/opt/srs-config"
BACKUP_DIR="/opt/ezstream-backups/$(date +%Y%m%d_%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}ℹ️ $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        exit 1
    fi
    log_success "Running as root"
}

# Backup existing files
backup_existing() {
    log_info "Creating backup of existing files..."
    
    mkdir -p "$BACKUP_DIR"
    
    if [ -d "$AGENT_DIR" ]; then
        cp -r "$AGENT_DIR" "$BACKUP_DIR/ezstream-agent"
        log_success "Agent files backed up"
    fi
    
    if [ -f "$SRS_CONFIG_DIR/srs.conf" ]; then
        cp "$SRS_CONFIG_DIR/srs.conf" "$BACKUP_DIR/srs.conf"
        log_success "SRS config backed up"
    fi
    
    log_success "Backup created at $BACKUP_DIR"
}

# Stop existing services
stop_services() {
    log_info "Stopping existing services..."
    
    # Stop agent
    if systemctl is-active --quiet ezstream-agent; then
        systemctl stop ezstream-agent
        log_success "EZStream agent stopped"
    fi
    
    # Stop any running FFmpeg processes
    pkill -f "ffmpeg.*ezstream" || true
    log_success "FFmpeg processes stopped"
}

# Deploy new files
deploy_files() {
    log_info "Deploying new robust streaming files..."
    
    # Ensure agent directory exists
    mkdir -p "$AGENT_DIR"
    
    # Copy new files (assuming they're in current directory)
    cp robust_stream_manager.py "$AGENT_DIR/"
    cp srs_config_manager.py "$AGENT_DIR/"
    cp stream_integration.py "$AGENT_DIR/"
    cp test_robust_streaming.py "$AGENT_DIR/"
    
    # Set permissions
    chmod +x "$AGENT_DIR"/*.py
    chown -R root:root "$AGENT_DIR"
    
    log_success "New files deployed"
}

# Test SRS connectivity
test_srs() {
    log_info "Testing SRS connectivity..."
    
    # Check if SRS container is running
    if docker ps | grep -q ezstream-srs; then
        log_success "SRS container is running"
    else
        log_error "SRS container is not running"
        return 1
    fi
    
    # Test SRS API
    if curl -s http://localhost:1985/api/v1/versions > /dev/null; then
        log_success "SRS API is responding"
    else
        log_error "SRS API is not responding"
        return 1
    fi
    
    return 0
}

# Run system tests
run_tests() {
    log_info "Running system tests..."
    
    cd "$AGENT_DIR"
    
    if python3 test_robust_streaming.py; then
        log_success "All tests passed"
        return 0
    else
        log_error "Some tests failed"
        return 1
    fi
}

# Update SRS configuration
update_srs_config() {
    log_info "Updating SRS configuration..."
    
    # Backup current config
    if [ -f "$SRS_CONFIG_DIR/srs.conf" ]; then
        cp "$SRS_CONFIG_DIR/srs.conf" "$SRS_CONFIG_DIR/srs.conf.backup.$(date +%s)"
    fi
    
    # Add forward section if not exists
    if ! grep -q "# Dynamic forwards managed by EZStream Agent" "$SRS_CONFIG_DIR/srs.conf"; then
        log_info "Adding dynamic forward section to SRS config..."
        
        # Insert before the last closing brace of vhost
        sed -i '/^vhost __defaultVhost__ {/,/^}$/ {
            /^}$/ i\
\
    # Dynamic forwards managed by EZStream Agent\
    # This section will be updated automatically
        }' "$SRS_CONFIG_DIR/srs.conf"
        
        log_success "SRS config updated"
    else
        log_info "SRS config already has dynamic forward section"
    fi
}

# Restart services
restart_services() {
    log_info "Restarting services..."
    
    # Reload systemd
    systemctl daemon-reload
    
    # Restart SRS container
    docker restart ezstream-srs
    sleep 5
    
    # Start agent
    systemctl start ezstream-agent
    sleep 3
    
    # Check status
    if systemctl is-active --quiet ezstream-agent; then
        log_success "EZStream agent started successfully"
    else
        log_error "Failed to start EZStream agent"
        return 1
    fi
    
    return 0
}

# Verify deployment
verify_deployment() {
    log_info "Verifying deployment..."
    
    # Check agent status
    if systemctl is-active --quiet ezstream-agent; then
        log_success "Agent is running"
    else
        log_error "Agent is not running"
        return 1
    fi
    
    # Check agent logs for errors
    if journalctl -u ezstream-agent --since "1 minute ago" | grep -q "ERROR\|CRITICAL"; then
        log_warning "Found errors in agent logs"
        journalctl -u ezstream-agent --since "1 minute ago" | grep "ERROR\|CRITICAL" | tail -5
    else
        log_success "No errors in agent logs"
    fi
    
    # Check SRS status
    if docker ps | grep -q ezstream-srs; then
        log_success "SRS container is running"
    else
        log_error "SRS container is not running"
        return 1
    fi
    
    return 0
}

# Rollback function
rollback() {
    log_error "Deployment failed, rolling back..."
    
    if [ -d "$BACKUP_DIR" ]; then
        # Stop services
        systemctl stop ezstream-agent || true
        
        # Restore files
        if [ -d "$BACKUP_DIR/ezstream-agent" ]; then
            rm -rf "$AGENT_DIR"
            cp -r "$BACKUP_DIR/ezstream-agent" "$AGENT_DIR"
            log_info "Agent files restored"
        fi
        
        if [ -f "$BACKUP_DIR/srs.conf" ]; then
            cp "$BACKUP_DIR/srs.conf" "$SRS_CONFIG_DIR/srs.conf"
            log_info "SRS config restored"
        fi
        
        # Restart services
        docker restart ezstream-srs
        systemctl start ezstream-agent
        
        log_success "Rollback completed"
    else
        log_error "No backup found for rollback"
    fi
}

# Main deployment function
main() {
    log_info "Starting robust streaming deployment..."
    
    # Set trap for rollback on error
    trap rollback ERR
    
    # Run deployment steps
    check_root
    backup_existing
    stop_services
    deploy_files
    
    if test_srs; then
        log_success "SRS tests passed"
    else
        log_error "SRS tests failed"
        exit 1
    fi
    
    update_srs_config
    
    if run_tests; then
        log_success "System tests passed"
    else
        log_error "System tests failed"
        exit 1
    fi
    
    restart_services
    
    if verify_deployment; then
        log_success "Deployment verification passed"
    else
        log_error "Deployment verification failed"
        exit 1
    fi
    
    # Clear trap
    trap - ERR
    
    echo ""
    echo "🎉 Robust Streaming System Deployed Successfully!"
    echo "=============================================="
    echo ""
    echo "📋 Next Steps:"
    echo "1. Monitor agent logs: journalctl -u ezstream-agent -f"
    echo "2. Test streaming with Laravel"
    echo "3. Check SRS streams: curl http://localhost:1985/api/v1/streams"
    echo ""
    echo "📁 Backup location: $BACKUP_DIR"
    echo ""
}

# Run main function
main "$@"
