const express = require('express');
const axios = require('axios');
const { JSDOM } = require('jsdom');
const cron = require('node-cron');
const path = require('path');

// Set timezone to Melbourne, Australia
process.env.TZ = 'Australia/Melbourne';

const app = express();
const PORT = process.env.PORT || 5000;

// Cache to store scraped data
let cachedData = null;
let lastUpdated = null;

// Serve static files
app.use(express.static('.'));

// Scrape CFA fire data
async function scrapeCFAData() {
  try {
    console.log('🔄 Scraping CFA fire data...');
    
    const response = await axios.get(
      'https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/north-central-fire-district',
      {
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
        }
      }
    );

    const dom = new JSDOM(response.data);
    const document = dom.window.document;
    
    // Look for the main data table
    const table = document.querySelector('#gvFireBansAndRatingsMunicipalityList, table[id*="Fire"], table[class*="fire"], .fire-table, table');
    
    if (!table) {
      console.log('⚠️ Fire data table not found, scraping general content...');
      
      // Fallback: look for any relevant fire information
      const fireInfo = [];
      const rows = document.querySelectorAll('tr');
      
      rows.forEach(row => {
        const cells = row.querySelectorAll('td, th');
        if (cells.length >= 2) {
          const rowData = Array.from(cells).map(cell => cell.textContent.trim()).filter(text => text);
          if (rowData.some(text => text.toLowerCase().includes('fire') || text.toLowerCase().includes('rating') || text.toLowerCase().includes('ban'))) {
            fireInfo.push({
              location: rowData[0] || 'Unknown',
              status: rowData[1] || 'Unknown',
              date: rowData[2] || new Date().toLocaleDateString('en-AU', { timeZone: 'Australia/Melbourne' }),
              details: rowData.slice(3).join(' ') || 'No additional details'
            });
          }
        }
      });

      if (fireInfo.length === 0) {
        // If no table data, create sample structure based on CFA format
        fireInfo.push({
          location: 'North Central Fire District',
          status: 'Data being loaded...',
          date: new Date().toLocaleDateString('en-AU', { timeZone: 'Australia/Melbourne' }),
          details: 'Please check CFA website for current information'
        });
      }

      return fireInfo;
    }

    // Parse table data
    const rows = table.querySelectorAll('tr');
    const fireData = [];

    rows.forEach((row, index) => {
      const cells = row.querySelectorAll('td');
      if (cells.length > 0) {
        fireData.push({
          location: cells[0]?.textContent.trim() || `Location ${index}`,
          status: cells[1]?.textContent.trim() || 'Unknown',
          date: cells[2]?.textContent.trim() || new Date().toLocaleDateString('en-AU', { timeZone: 'Australia/Melbourne' }),
          details: cells[3]?.textContent.trim() || 'No additional details'
        });
      }
    });

    console.log(`✅ Successfully scraped ${fireData.length} entries`);
    return fireData;

  } catch (error) {
    console.error('❌ Error scraping CFA data:', error.message);
    return [{
      location: 'North Central Fire District',
      status: 'Error loading data',
      date: new Date().toLocaleDateString('en-AU', { timeZone: 'Australia/Melbourne' }),
      details: 'Unable to fetch current fire information. Please try again later.'
    }];
  }
}

// Update cached data
async function updateCache() {
  console.log('📦 Updating cache...');
  cachedData = await scrapeCFAData();
  lastUpdated = new Date();
  console.log(`✅ Cache updated at ${lastUpdated.toLocaleString('en-AU', { timeZone: 'Australia/Melbourne' })} (Melbourne time)`);
}

// API endpoint to get fire data
app.get('/api/fire-data', async (req, res) => {
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  
  if (!cachedData) {
    await updateCache();
  }
  
  res.json({
    data: cachedData,
    lastUpdated: lastUpdated,
    nextUpdate: getNextUpdateTime()
  });
});

// Get next scheduled update time (Melbourne timezone)
function getNextUpdateTime() {
  const now = new Date();
  const next = new Date(now);
  
  // Get current hour in Melbourne timezone
  const melbourneHour = new Date().toLocaleString('en-AU', { 
    timeZone: 'Australia/Melbourne', 
    hour: 'numeric', 
    hour12: false 
  });
  const currentHour = parseInt(melbourneHour);
  
  // Next update at 6 AM or 6 PM Melbourne time
  if (currentHour < 6) {
    next.setHours(6, 0, 0, 0);
  } else if (currentHour < 18) {
    next.setHours(18, 0, 0, 0);
  } else {
    next.setDate(next.getDate() + 1);
    next.setHours(6, 0, 0, 0);
  }
  
  return next;
}

// Schedule cache updates twice daily (6 AM and 6 PM Melbourne time)
cron.schedule('0 6,18 * * *', () => {
  console.log('⏰ Scheduled cache update triggered (Melbourne time)');
  updateCache();
}, {
  timezone: 'Australia/Melbourne'
});

// Serve main page
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

// Initialize cache on startup
updateCache();

// Start server
app.listen(PORT, '0.0.0.0', () => {
  console.log(`🚀 CFA Fire Forecast server running on port ${PORT}`);
  console.log(`📅 Cache updates scheduled for 6 AM and 6 PM daily (Melbourne time)`);
  console.log(`🕐 Current Melbourne time: ${new Date().toLocaleString('en-AU', { timeZone: 'Australia/Melbourne' })}`);
});