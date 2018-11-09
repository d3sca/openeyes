<script src="<?= Yii::app()->assetManager->createUrl('js/oescape/plotly-Meds.js')?>"></script>

<div id="js-hs-chart-Meds" class="highchart-area" data-highcharts-chart="2" dir="ltr" style="min-width: 500px; left: 0px; top: 0px;">
    <div id="plotly-Meds-right" class="plotly-Meds plotly-right plotly-section" data-eye-side="right"></div>
    <div id="plotly-Meds-left" class="plotly-Meds plotly-left plotly-section" data-eye-side="left" style="display: none;"></div>
</div>

<script type="text/javascript">
  $(document).ready(function () {
    var meds_data = <?= CJavaScript::encode($this->getMedicationList()); ?>;
    var sides = ['left', 'right'];
    var layout_meds = JSON.parse(JSON.stringify(layout_plotly));
    //plotly
    for (var side of sides){
      var data = [];
      var text_trace = {
        x: [],
        y: [],
        text: [],
        mode: 'text',
        hoverinfo: 'skip',
        textposition: "middle right",
        textfont: {
          color:'#ffffff',
        },
      };

      for (key in meds_data[side]){
        text_trace['x'].push(new Date(meds_data[side][key][0]['low']));
        text_trace['y'].push(key);
        text_trace['text'].push(key);
        for (i in meds_data[side][key]){
          var x_range = meds_data[side][key][i]['high'] - meds_data[side][key][i]['low'];
          var start_time = meds_data[side][key][i]['low'];
          var end_time = meds_data[side][key][i]['high'];
          var x_values = [];
          var y_values = [];
          for (var d = start_time; d < end_time; d= d+14*86400*1000){
            x_values.push(new Date(d));
            y_values.push(key);
          }
          x_values.push(new Date(end_time));
          y_values.push(key);
          var trace = {
            mode: "lines",
            x: x_values,
            y: y_values,
            hovertext: key+"<br>"+x_values[0].toLocaleDateString()+"-"+x_values[1].toLocaleDateString(),
            hoverinfo: 'text',
            hoverlabel: trace_hoverlabel,
            line: {
              width: 20,
              color: (side=='right')?'#5f8e41':'#983e3e',
            }
          };
          data.push(trace);
        }
      }
      data.push(text_trace);
      layout_meds['title'] = "Medications, IOP, VA & MD";
      layout_meds['yaxis'] = meds_yaxis;
      layout_meds['height'] = 25*Object.keys(meds_data[side]).length+130;
      layout_meds['showlegend'] = false;
      layout_meds['xaxis'] = meds_xaxis;
      layout_meds['yaxis']['range'] = [Object.keys(meds_data[side]).length-0.5, -1];

      Plotly.newPlot('plotly-Meds-'+side, data, layout_meds, options_plotly);
    }
  });
</script>