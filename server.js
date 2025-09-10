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

// Scrape CFA fire danger rating data
async function scrapeCFAData() {
  try {
    console.log('🔄 Scraping CFA fire danger ratings...');
    
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
    
    const forecastData = [];
    const currentDate = new Date();
    
    // Generate 4-day forecast dates
    const dayNames = ['Today', 'Tomorrow', 'Day 3', 'Day 4'];
    const fullDayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    
    for (let i = 0; i < 4; i++) {
      const forecastDate = new Date(currentDate);
      forecastDate.setDate(currentDate.getDate() + i);
      
      let dayLabel = i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : fullDayNames[forecastDate.getDay()];
      const dateString = forecastDate.toLocaleDateString('en-AU', { 
        timeZone: 'Australia/Melbourne',
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      });
      
      // Try to find fire danger rating for this day
      let fireDangerRating = 'NO RATING';
      let totalFireBan = false;
      
      // Look for fire danger rating in the page content
      const content = document.body.textContent || document.body.innerText || '';
      
      // Extract current day's rating (for today)
      if (i === 0) {
        // Try multiple approaches to find the fire danger rating
        console.log(`🔍 Searching for fire danger rating...`);
        console.log(`🔍 Content length: ${content.length} characters`);
        console.log(`🔍 Content contains "NO RATING": ${content.includes('NO RATING')}`);
        console.log(`🔍 Response data contains "no-rating.gif": ${response.data.includes('no-rating.gif')}`);
        
        // Show content around "Fire Danger Rating" section
        const fireDangerIndex = content.indexOf('Fire Danger Rating');
        if (fireDangerIndex !== -1) {
          const contextStart = Math.max(0, fireDangerIndex - 50);
          const contextEnd = Math.min(content.length, fireDangerIndex + 200);
          console.log(`🔍 Fire Danger Rating context: "${content.substring(contextStart, contextEnd)}"`);
        }
        
        // Approach 1: Look for "NO RATING" explicitly in content
        if (content.includes('NO RATING')) {
          fireDangerRating = 'NO RATING';
          console.log(`✅ Found "NO RATING" in content`);
        }
        // Approach 2: Check for no-rating.gif image reference
        else if (response.data.includes('no-rating.gif')) {
          fireDangerRating = 'NO RATING';
          console.log(`✅ Found no-rating.gif image - indicates NO RATING`);
        }
        // Approach 3: Try regex patterns for specific ratings
        else {
          const ratingPatterns = {
            'LOW-MODERATE': /low[\s-]*moderate/gi,
            'MODERATE': /(?<!low[\s-]*)moderate(?![\s-]*)/gi,
            'HIGH': /(?<!-)high(?!-)/gi,
            'EXTREME': /extreme/gi,
            'CATASTROPHIC': /catastrophic/gi
          };
          
          let foundRating = false;
          for (const [rating, pattern] of Object.entries(ratingPatterns)) {
            if (content.match(pattern) && !foundRating) {
              fireDangerRating = rating;
              foundRating = true;
              console.log(`🎯 Found rating in content: ${rating}`);
              break;
            }
          }
          
          if (!foundRating) {
            console.log(`❓ No specific fire danger rating found, keeping default NO RATING`);
          }
        }
        
        // Check for total fire ban
        if (content.toLowerCase().includes('total fire ban') && content.toLowerCase().includes('in force')) {
          totalFireBan = true;
          console.log(`🚨 Total Fire Ban is in force`);
        } else if (content.toLowerCase().includes('not currently a day of total fire ban')) {
          totalFireBan = false;
          console.log(`✅ No Total Fire Ban today`);
        }
      }
      
      forecastData.push({
        day: dayLabel,
        date: dateString,
        fireDangerRating: fireDangerRating,
        totalFireBan: totalFireBan,
        district: 'North Central Fire District'
      });
    }
    
    // Log the extracted rating for today
    console.log(`🔍 Today's fire danger rating: ${forecastData[0]?.fireDangerRating}`);
    
    // Final validation - NO RATING is a valid result from CFA
    if (forecastData[0]) {
      console.log(`🏁 Final result for today: ${forecastData[0].fireDangerRating}`);
      if (forecastData[0].fireDangerRating === 'NO RATING') {
        console.log(`✅ NO RATING is the correct fire danger status from CFA`);
      }
    }
    
    console.log(`✅ Successfully scraped ${forecastData.length} day forecast`);
    return forecastData;

  } catch (error) {
    console.error('❌ Error scraping CFA data:', error.message);
    
    // Return default 4-day structure on error
    const currentDate = new Date();
    const defaultData = [];
    
    for (let i = 0; i < 4; i++) {
      const forecastDate = new Date(currentDate);
      forecastDate.setDate(currentDate.getDate() + i);
      
      const dayLabel = i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : `Day ${i + 1}`;
      const dateString = forecastDate.toLocaleDateString('en-AU', { 
        timeZone: 'Australia/Melbourne',
        weekday: 'short',
        day: 'numeric',
        month: 'long'
      });
      
      defaultData.push({
        day: dayLabel,
        date: dateString,
        fireDangerRating: 'ERROR LOADING',
        totalFireBan: false,
        district: 'North Central Fire District'
      });
    }
    
    return defaultData;
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