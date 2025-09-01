
let searchTimeout = null;

function initSearch() {
    const searchInput = document.getElementById('headerSearchInput');
    const searchResults = document.getElementById('searchResults');
    
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Hide results if query is empty
        if (query.length === 0) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }
        
        // Set new timeout to search after user stops typing
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (searchResults && !searchResults.contains(e.target) && e.target !== searchInput) {
            searchResults.style.display = 'none';
        }
    });
    
    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            searchInput.blur();
        }
    });

    // Initialize mobile search
    initMobileSearch();
}

async function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    
    if (!query || query.length < 2) {
        if (searchResults) {
            searchResults.style.display = 'none';
        }
        return;
    }
    
    try {
        const response = await fetch(`PHP/search.php?query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.results.length > 0) {
            displaySearchResults(data.results);
        } else {
            searchResults.innerHTML = '<div class="search-no-results">No results found</div>';
            searchResults.style.display = 'block';
        }
    } catch (error) {
        console.error('Search error:', error);
        searchResults.innerHTML = '<div class="search-no-results">Error performing search</div>';
        searchResults.style.display = 'block';
    }
}

function displaySearchResults(results) {
    const searchResults = document.getElementById('searchResults');
    searchResults.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.innerHTML = `
            <img src="${result.profile_pic}" alt="${result.display_name}" onerror="this.src='https://via.placeholder.com/50'">
            <div class="search-result-info">
                <div class="search-result-name">${result.display_name}</div>
                <div class="search-result-type">${result.type.charAt(0).toUpperCase() + result.type.slice(1)}</div>
            </div>
        `;
        
        item.addEventListener('click', () => {
            window.location.href = result.url;
        });
        
        searchResults.appendChild(item);
    });
    
    searchResults.style.display = 'block';
}

function initMobileSearch() {
    const mobileSearchInput = document.getElementById('mobileSearchInput');
    const mobileSearchResults = document.getElementById('mobileSearchResults');
    
    if (!mobileSearchInput) return;
    
    mobileSearchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        // Hide results if query is empty
        if (query.length === 0) {
            mobileSearchResults.style.display = 'none';
            mobileSearchResults.innerHTML = '';
            return;
        }
        
        // Set new timeout to search after user stops typing
        searchTimeout = setTimeout(() => {
            performMobileSearch(query);
        }, 300);
    });
}

async function performMobileSearch(query) {
    const mobileSearchResults = document.getElementById('mobileSearchResults');
    
    if (!query || query.length < 2) {
        mobileSearchResults.style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`PHP/search.php?query=${encodeURIComponent(query)}`);
        const data = await response.json();
        
        if (data.success && data.results.length > 0) {
            displayMobileSearchResults(data.results);
        } else {
            mobileSearchResults.innerHTML = '<div class="search-no-results">No results found</div>';
            mobileSearchResults.style.display = 'block';
        }
    } catch (error) {
        console.error('Mobile search error:', error);
        mobileSearchResults.innerHTML = '<div class="search-no-results">Error performing search</div>';
        mobileSearchResults.style.display = 'block';
    }
}

function displayMobileSearchResults(results) {
    const mobileSearchResults = document.getElementById('mobileSearchResults');
    mobileSearchResults.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.innerHTML = `
            <img src="${result.profile_pic}" alt="${result.display_name}" onerror="this.src='https://via.placeholder.com/50'">
            <div class="search-result-info">
                <div class="search-result-name">${result.display_name}</div>
                <div class="search-result-type">${result.type.charAt(0).toUpperCase() + result.type.slice(1)}</div>
            </div>
        `;
        
        item.addEventListener('click', () => {
            window.location.href = result.url;
            toggleSearchOverlay(); // Close the overlay after selection
        });
        
        mobileSearchResults.appendChild(item);
    });
    
    mobileSearchResults.style.display = 'block';
}

// Initialize search when DOM is loaded
document.addEventListener('DOMContentLoaded', initSearch);
