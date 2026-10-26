const { execSync } = require('child_process');
const path = require('path');

module.exports = async () => {
  if (process.env.AIT_SKIP_RESET) return;
  try {
    execSync('docker compose exec -T redis redis-cli FLUSHALL', { cwd: path.resolve(__dirname, '../..'), stdio: 'ignore' });
  } catch (error) {
    console.warn('Could not flush Redis (is the Docker stack running?):', error.message);
  }
};
