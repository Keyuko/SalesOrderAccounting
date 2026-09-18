<div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <form method="GET" action="{{ url()->current() }}" style="display: flex; gap: 15px; align-items: flex-end;">
        <div>
            <label style="display: block; font-size: 14px; margin-bottom: 5px;">Filter Range</label>
            <select id="filter_type" style="padding: 8px; border: 1px solid #E2E8F0; border-radius: 6px; width: 150px;" onchange="handleFilterChange()">
                <option value="custom" {{ request('start_date') ? 'selected' : '' }}>Custom Date</option>
                <option value="this_month" {{ !request('start_date') ? 'selected' : '' }}>This Month</option>
                <option value="this_year">This Year</option>
            </select>
        </div>
        
        <div id="custom_dates" style="display: flex; gap: 15px;">
            <div>
                <label style="display: block; font-size: 14px; margin-bottom: 5px;">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ request('start_date', date('Y-m-01')) }}" style="padding: 8px; border: 1px solid #E2E8F0; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-size: 14px; margin-bottom: 5px;">End Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ request('end_date', date('Y-m-t')) }}" style="padding: 8px; border: 1px solid #E2E8F0; border-radius: 6px;">
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; height: 38px;">Apply Filter</button>
            <a href="{{ url()->current() }}" class="btn" style="padding: 8px 16px; height: 38px; line-height: 20px; background:#f1f5f9; color:#333; text-decoration:none; display:inline-block;">Reset</a>
        </div>
    </form>
</div>

<script>
    function handleFilterChange() {
        const type = document.getElementById('filter_type').value;
        const startDate = document.getElementById('start_date');
        const endDate = document.getElementById('end_date');
        
        const today = new Date();
        const y = today.getFullYear();
        const m = today.getMonth();

        if (type === 'this_month') {
            startDate.value = new Date(y, m, 1).toISOString().split('T')[0];
            endDate.value = new Date(y, m + 1, 0).toISOString().split('T')[0];
        } else if (type === 'this_year') {
            startDate.value = new Date(y, 0, 1).toISOString().split('T')[0];
            endDate.value = new Date(y, 11, 31).toISOString().split('T')[0];
        }
    }

    // Initialize if no request param
    if (!window.location.search.includes('start_date')) {
        handleFilterChange();
    }
</script>
