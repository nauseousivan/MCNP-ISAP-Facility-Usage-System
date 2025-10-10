const { execSync } = require('child_process');

try {
  console.log('🐾 Starting auto push...');
  execSync('git add .', { stdio: 'inherit' });
  execSync('git commit -m "Auto update from script 🐱"', { stdio: 'inherit' });
  execSync('git push', { stdio: 'inherit' });
  console.log('✅ Push complete!');
} catch (err) {
  console.error('❌ Error during auto push:', err.message);
}
