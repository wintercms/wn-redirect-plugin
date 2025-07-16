<script>
    data = {
        labels: <?= $labels ?>,
        datasets: <?= $dataSets ?>
    };

    config = {
        type: 'bar',
        data: data,
        options: {
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true
                }
            }
        }
    };

    hitsPerDayChart = new Chart(document.getElementById('hitsPerDayChart'), config);
</script>
<div class="row" style="margin: 0">
    <div class="col-lg-10">
        <h3><?= e(trans('winter.redirect::lang.statistics.hits_per_day')); ?></h3>
    </div>
    <div class="col-lg-2">
        <div class="form-group dropdown-field">
            <label class="control-label hidden" for="periodMonthYear">
                Period
            </label>
            <select id="periodMonthYear"
                    class="form-control"
                    name="period-month-year"
                    data-request="onSelectPeriodMonthYear"
                    data-request-before-update="hitsPerDayChart.destroy()">
                <?php foreach ($monthYearOptions as $value => $label): ?>
                <?php $selected = $monthYearSelected === $value ? ' selected' : ''; ?>
                <option value="<?= $value ?>"<?=$selected?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<canvas id="hitsPerDayChart" width="1024" height="250"></canvas>

