import 'package:flutter/material.dart';

abstract class AppColors {
  static const Color rideService    = Color(0xFFF4511E); // Colors.deepOrange.shade600
  static const Color parcelService  = Color(0xFF388E3C); // Colors.green.shade700
  static const Color offlineWarning = Color(0xFFFFA000); // Colors.amber.shade700

  // Semantic tokens — values match the literals they replace (no visual change).
  static const Color successGreen   = Color(0xFF4CAF50); // == Colors.green
  static const Color ratingAmber    = Color(0xFFFFC107); // == Colors.amber
  // Shimmer skeleton palette (light / dark).
  static const Color shimmerBaseLight      = Color(0xFFE0E0E0);
  static const Color shimmerHighlightLight = Color(0xFFF5F5F5);
  static const Color shimmerBaseDark       = Color(0xFF303030);
  static const Color shimmerHighlightDark  = Color(0xFF404040);
}
