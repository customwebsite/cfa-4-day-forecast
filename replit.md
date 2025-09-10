# CFA Fire Danger Forecast

## Overview

This is a web application that provides real-time fire danger forecasts for Victoria, Australia by scraping data from the Country Fire Authority (CFA) website. The application displays fire danger ratings, total fire ban status, and weather conditions for different municipalities in the North Central Fire District. It features automated data collection, caching for performance, and a responsive web interface with color-coded danger levels.

## User Preferences

Preferred communication style: Simple, everyday language.

## System Architecture

### Frontend Architecture
- **Static HTML/CSS/JavaScript**: Single-page application served directly from the root directory
- **Responsive Design**: CSS-based responsive layout optimized for desktop and mobile devices
- **Color-coded Interface**: Visual fire danger indicators using gradient backgrounds and status colors
- **Real-time Updates**: Client-side JavaScript fetches data from the backend API

### Backend Architecture
- **Express.js Server**: Lightweight Node.js web server handling both static file serving and API endpoints
- **Web Scraping Engine**: Uses Axios for HTTP requests and JSDOM for HTML parsing to extract fire data
- **Automated Data Collection**: Node-cron scheduler runs periodic scraping jobs to keep data current
- **In-memory Caching**: Simple caching mechanism to reduce load on external CFA website
- **Fallback Parsing**: Robust scraping logic with multiple parsing strategies for different page layouts

### Data Processing
- **HTML Parsing**: JSDOM-based extraction of fire danger tables and ratings from CFA website
- **Data Transformation**: Converts scraped HTML data into structured JSON format for API consumption
- **Error Handling**: Graceful fallbacks when primary data sources are unavailable

### API Design
- **RESTful Endpoints**: Clean API structure for fetching fire danger data
- **JSON Response Format**: Standardized data format for frontend consumption
- **Status Tracking**: Includes metadata like last update timestamps and data freshness indicators

## External Dependencies

### Core Web Scraping
- **CFA Website**: Primary data source at `cfa.vic.gov.au` for fire danger ratings and total fire ban information
- **Axios**: HTTP client library for making requests to external websites
- **JSDOM**: Server-side DOM implementation for parsing HTML content

### Server Infrastructure
- **Express.js**: Web application framework for Node.js
- **Node-cron**: Task scheduler for automated data collection

### Development Dependencies
- **Node.js Runtime**: JavaScript runtime environment for server execution

### Data Sources
- **North Central Fire District**: Specific CFA region data endpoint for municipal fire ratings
- **User-Agent Spoofing**: Mimics browser requests to avoid bot detection on CFA website