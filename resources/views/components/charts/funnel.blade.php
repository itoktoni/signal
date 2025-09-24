@props([
    'id' => 'funnelChart',
    'title' => 'Funnel Chart',
    'data' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de']
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c}%'
        },
        legend: {
            data: @json($data).map(item => item.name),
            top: 50
        },
        series: [{
            name: 'Funnel',
            type: 'funnel',
            left: '10%',
            top: 80,
            bottom: 60,
            width: '80%',
            min: 0,
            max: 100,
            minSize: '0%',
            maxSize: '100%',
            sort: 'descending',
            gap: 2,
            label: {
                show: true,
                position: 'inside',
                formatter: '{b}: {c}%'
            },
            labelLine: {
                length: 10,
                lineStyle: {
                    width: 1,
                    type: 'solid'
                }
            },
            itemStyle: {
                borderColor: '#fff',
                borderWidth: 1
            },
            emphasis: {
                label: {
                    fontSize: 20
                }
            },
            data: @json($data).map(function(item, index) {
                return {
                    value: item.value,
                    name: item.name,
                    itemStyle: {
                        color: colors[index % colors.length]
                    }
                };
            })
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>