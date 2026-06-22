class RemainingDistanceModel {
  double? distance;
  String? distanceText;
  String? duration;
  int? durationSec;
  String? durationInTraffic;
  int? durationInTrafficSec;
  String? status;
  String? driveMode;
  String? encodedPolyline;

  RemainingDistanceModel(
      {this.distance,
        this.distanceText,
        this.duration,
        this.durationSec,
        this.status,
        this.driveMode,
        this.encodedPolyline,
        this.durationInTraffic,
        this.durationInTrafficSec
      });

  RemainingDistanceModel.fromJson(Map<String, dynamic> json) {
    if(json['distance'] != null){
      try{
        distance = json['distance'].toDouble();
      }catch(e){
        distance = double.tryParse(json['distance'].toString()) ?? 0;
      }
    }
    distanceText = json['distance_text'];
    duration = json['duration'];
    durationSec = json['duration_sec'];
    status = json['status'];
    driveMode = json['drive_mode'];
    encodedPolyline = json['encoded_polyline'];
    durationInTraffic = json['duration_in_traffic'];
    durationInTrafficSec = json['duration_in_traffic_sec'];
  }

}
