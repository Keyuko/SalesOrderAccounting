<!-- Checklist Modal -->
<div id="checklistModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1100; align-items:center; justify-content:center; overflow-y:auto; padding: 20px;">
    <div style="background:white; padding:24px; border-radius:12px; width:800px; max-height: 90vh; overflow-y:auto; margin:auto;">
        <h3 id="checklistTitle">Buku Muat (Vehicle Checklist)</h3>
        
        <form id="checklistForm" method="POST" action="">
            @csrf
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="field-group">
                    <label>No Polisi / Plat Kendaraan</label>
                    <input type="text" name="no_pol" id="chk_no_pol" class="input-field" required>
                </div>
                <div class="field-group">
                    <label>Nama Pengemudi</label>
                    <input type="text" name="driver_name" id="chk_driver_name" class="input-field" required>
                </div>
            </div>

            <h4>Kelengkapan</h4>
            <table style="width: 100%; text-align: left; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <th style="padding: 8px;">Item</th>
                        <th style="padding: 8px; width: 120px;">Status</th>
                        <th style="padding: 8px;">Catatan (Note)</th>
                    </tr>
                </thead>
                <tbody id="checklistTableBody">
                    <!-- Rows will be populated by JS or static HTML -->
                    @php
                    $items = [
                        ['id' => 'sim', 'label' => 'SIM Pengemudi'],
                        ['id' => 'stnk', 'label' => 'STNK Kendaraan'],
                        ['id' => 'kir', 'label' => 'Buku KIR'],
                        ['id' => 'segitiga', 'label' => 'Segitiga Pengaman'],
                        ['id' => 'apar', 'label' => 'APAR'],
                        ['id' => 'apd', 'label' => 'APD (Helm/Sepatu)'],
                        ['id' => 'p3k', 'label' => 'Kotak P3K'],
                        ['id' => 'kondisi_ban', 'label' => 'Kondisi Ban'],
                        ['id' => 'ban_cadangan', 'label' => 'Ban Cadangan'],
                        ['id' => 'dongkrak', 'label' => 'Dongkrak'],
                        ['id' => 'kunci_std', 'label' => 'Kunci Standar'],
                        ['id' => 'sabuk', 'label' => 'Sabuk Pengaman'],
                        ['id' => 'lampu', 'label' => 'Lampu-lampu'],
                        ['id' => 'wiper', 'label' => 'Wiper'],
                        ['id' => 'spion', 'label' => 'Kaca Spion'],
                        ['id' => 'b3', 'label' => 'Atribut B3 (jika ada)']
                    ];
                    @endphp

                    @foreach($items as $item)
                    <tr>
                        <td style="padding: 8px;">{{ $item['label'] }}</td>
                        <td style="padding: 8px;">
                            <select name="{{ $item['id'] }}" id="chk_{{ $item['id'] }}" class="input-field" style="padding: 4px;">
                                <option value="Sesuai">Sesuai</option>
                                <option value="Tidak">Tidak</option>
                            </select>
                        </td>
                        <td style="padding: 8px;">
                            <input type="text" name="note_{{ $item['id'] }}" id="chk_note_{{ $item['id'] }}" class="input-field" style="padding: 4px;" placeholder="Catatan...">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="field-group" style="margin-bottom: 20px;">
                <label>Catatan Tambahan</label>
                <textarea name="catatan" id="chk_catatan" class="input-field" rows="3"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                @if(Auth::user()->role == 'delivery')
                <button type="submit" class="btn btn-primary" id="btnSaveChecklist">Save Buku Muat</button>
                @endif
                <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeChecklistModal()">Close</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openChecklistModal(deliveryId, checklistData) {
        // Set action form
        document.getElementById('checklistForm').action = '/deliveries/' + deliveryId + '/checklist';
        
        // Reset form first
        document.getElementById('checklistForm').reset();
        
        const isDeliveryRole = {{ Auth::user()->role == 'delivery' ? 'true' : 'false' }};
        const allInputs = document.querySelectorAll('#checklistForm input, #checklistForm select, #checklistForm textarea');
        
        // Enable all first
        allInputs.forEach(el => el.disabled = false);
        document.getElementById('btnSaveChecklist')?.removeAttribute('style');

        // If data exists, populate it
        if(checklistData && Object.keys(checklistData).length > 0) {
            document.getElementById('chk_no_pol').value = checklistData.no_pol || '';
            document.getElementById('chk_driver_name').value = checklistData.driver_name || '';
            document.getElementById('chk_catatan').value = checklistData.catatan || '';
            
            // Populate items
            @foreach($items as $item)
            if(document.getElementById('chk_{{ $item['id'] }}')) {
                document.getElementById('chk_{{ $item['id'] }}').value = checklistData.{{ $item['id'] }} || 'Sesuai';
            }
            if(document.getElementById('chk_note_{{ $item['id'] }}')) {
                document.getElementById('chk_note_{{ $item['id'] }}').value = checklistData.note_{{ $item['id'] }} || '';
            }
            @endforeach
            
            document.getElementById('checklistTitle').innerText = 'Buku Muat Details (Read Only)';
            
            // Disable all fields since it's already filled, or keep enabled for delivery edit?
            // User requested details button for view. Let's make it readonly if filled by someone else
            if (!isDeliveryRole) {
                allInputs.forEach(el => el.disabled = true);
            }
        } else {
            document.getElementById('checklistTitle').innerText = 'Isi Buku Muat (Vehicle Checklist)';
            if (!isDeliveryRole) {
                allInputs.forEach(el => el.disabled = true);
                if(document.getElementById('btnSaveChecklist')) document.getElementById('btnSaveChecklist').style.display = 'none';
            }
        }
        
        document.getElementById('checklistModal').style.display = 'flex';
    }

    function closeChecklistModal() {
        document.getElementById('checklistModal').style.display = 'none';
    }
</script>
