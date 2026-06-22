class MartProductModel {
  final String? id;
  final String? name;
  final String? image;
  final double price;

  MartProductModel({this.id, this.name, this.image, this.price = 0});

  factory MartProductModel.fromJson(Map<String, dynamic> json) {
    return MartProductModel(
      id: json['id']?.toString(),
      name: json['name']?.toString(),
      image: json['image']?.toString(),
      price: double.tryParse(json['price']?.toString() ?? '') ?? 0,
    );
  }
}
