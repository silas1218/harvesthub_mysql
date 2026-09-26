document.addEventListener('DOMContentLoaded', () => {
    
    async function loadAnalytics() {
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_analytics' })
            });
            const data = await res.json();
            
            if (!data.ok) {
                console.error("Failed to load analytics data.");
                return;
            }

            // 1. Update KPI Summary Cards
            document.getElementById('stat-active').textContent = data.stats.active_posts;
            document.getElementById('stat-completed').textContent = data.stats.completed_exchanges;
            document.getElementById('stat-claims').textContent = data.stats.total_claims;

            // 2. Format Data for the Chart
            // Extract the names and counts into separate arrays for Chart.js
            const labels = data.top_produce.map(item => item.ProduceName);
            const counts = data.top_produce.map(item => item.TotalPosts);

            // 3. Render the Bar Chart
            const ctx = document.getElementById('produceChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Listings Created',
                        data: counts,
                        backgroundColor: '#A0522D', // Using your var(--accent) brown color
                        borderRadius: 4, // Rounded corners on the bars
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#000',
                            bodyColor: '#475569',
                            borderColor: '#e2e8f0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }, // Don't show decimals on post counts
                            grid: { color: '#f1f5f9' }
                        },
                        x: {
                            grid: { display: false } // Hide vertical grid lines for cleaner look
                        }
                    }
                }
            });

        } catch (err) {
            console.error("Network error loading analytics:", err);
        }
    }

    // Initialize
    loadAnalytics();
});